//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"
#include <algorithm>

int quiet_system(string cmd) {
	cmd += " 1>/dev/null 2>/dev/null";
	return system(cmd.c_str());
}

bool firewall_check_or_add(const string& rule, const string& table = "filter") {
	stringstream cmd1, cmd2;
	cmd1 << "iptables -t " << table << " --check " << rule;
	cmd2 << "iptables -t " << table << " -A " << rule;
#ifdef DEBUG
	printf("Command: %s\n", cmd1.str().c_str());
#endif
	if (quiet_system(cmd1.str().c_str())) {
		printf("Adding rule: %s\n", cmd2.str().c_str());
		int n = system(cmd2.str().c_str());
		if (n != 0) {
			setError("Error adding firewall rule: %s, return value: %d", cmd2.str().c_str(), n);
			return false;
		}
	}
	return true;
}

bool firewall_delete_if_exists(const string& rule, const string& table = "filter") {
	stringstream cmd1, cmd2;
	cmd1 << "iptables -t " << table << " --check " << rule;
	cmd2 << "iptables -t " << table << " -D " << rule;
#ifdef DEBUG
	printf("Command: %s\n", cmd1.str().c_str());
#endif
	while (quiet_system(cmd1.str().c_str()) == 0) {
		printf("Deleting rule: %s\n", cmd2.str().c_str());
		int n = system(cmd2.str().c_str());
		if (n != 0) {
			setError("Error deleting firewall rule: %s, return value: %d", cmd2.str().c_str(), n);
			return false;
		}
	}
	return true;
}

class FirewallRules {
public:
	set<string> chains;
	vector<string> rules;
	vector<string> check_or_add;
	vector<string> delete_if_exists;
};

class FirewallState {
private:
map<string, FirewallRules> rules;

public:
	shared_ptr<Network> net;

	FirewallState(shared_ptr<Network>& pnet) {
		net = pnet;
	}

	void addChain(const string& chain, const string& table = "filter") {
		auto x = rules.find(table);
		if (x != rules.end()) {
			x->second.chains.insert(chain);
		} else {
			FirewallRules r;
			r.chains.insert(chain);
			rules[table] = r;
		}
	}
	void addRule(const string& rule, const string& table = "filter") {
		auto x = rules.find(table);
		if (x != rules.end()) {
			x->second.rules.push_back(rule);
		} else {
			FirewallRules r;
			r.rules.push_back(rule);
			rules[table] = r;
		}
	}
	void addCheckOrAdd(const string& rule, const string& table = "filter") {
		auto x = rules.find(table);
		if (x != rules.end()) {
			x->second.check_or_add.push_back(rule);
		} else {
			FirewallRules r;
			r.check_or_add.push_back(rule);
			rules[table] = r;
		}
	}
	void addDeleteIfExists(const string& rule, const string& table = "filter") {
		auto x = rules.find(table);
		if (x != rules.end()) {
			x->second.delete_if_exists.push_back(rule);
		} else {
			FirewallRules r;
			r.delete_if_exists.push_back(rule);
			rules[table] = r;
		}
	}

	bool apply() {
		// Create chains
		for (auto& t : rules) {
			for (auto& r : t.second.chains) {
				stringstream cmd;
				cmd << "iptables -t " << t.first << " -N " << r;
				quiet_system(cmd.str().c_str());
			}
		}

		bool ret = true;
		for (auto& t : rules) {
			for (auto& r : t.second.rules) {
				stringstream cmd;
				cmd << "iptables -t " << t.first << " " << r;
				int n = system(cmd.str().c_str());
#ifdef DEBUG
				printf("Command: %s\n", cmd.str().c_str());
#endif
				if (n != 0) {
					setError("Error adding firewall rule: %s\niptables return value: %d", cmd.str().c_str(), n);
					ret = false;
				}
			}
			for (auto& r : t.second.check_or_add) {
				if (!firewall_check_or_add(r, t.first)) {
					ret = false;
				}
			}
			for (auto& r : t.second.delete_if_exists) {
				if (!firewall_delete_if_exists(r, t.first)) {
					ret = false;
				}
			}
		}

		return ret;
	}
};

inline string GetPostRule(shared_ptr<Network>& net) {
	string network = mprintf("%s/%u", net->address.c_str(), net->netmask_int);
	stringstream rule;
	rule << "POSTROUTING -s " << network << " ! -d " << network << " -j MASQUERADE";
	return rule.str();
}

inline string GetPortForwardingTCPRule(shared_ptr<Network>& net, const string& chain) {
	stringstream rule;
	rule << "PREROUTING -i " << net->iface << " -p tcp -m tcp -m state --state NEW -j " << chain;
	return rule.str();
}

inline string GetPortForwardingUDPRule(shared_ptr<Network>& net, const string& chain) {
	stringstream rule;
	rule << "PREROUTING -i " << net->iface << " -p udp -j " << chain;
	return rule.str();
}

vector<shared_ptr<Machine>> GetNetworkMachines(const string& net) {
	AutoMutex(wdMutex);
	vector<shared_ptr<Machine>> ret;
	for (auto& x : machines) {
		if (x.second->network == net) {
			ret.push_back(x.second);
		}
	}
	return ret;
}

//-A TCP_COINS -i ens192 -p tcp -m tcp --dport 18302 -m state --state NEW -j DNAT --to-destination 10.3.0.10:18302
bool firewall_add_machine_rules(FirewallState& f, const string& chain, shared_ptr<Machine>& c) {
	MYSQL_RES * res = sql->Query(mprintf("SELECT * FROM `PortForwards` WHERE `MachineID`=%d", c->id));
	if (res == NULL) {
		setError("Error getting port forwards for machine %s", c->name.c_str());
		return false;
	}

	SC_Row row;
	while (sql->FetchRow(res, row)) {
		int intport = atoi(row.Get("InternalPort").c_str());
		int extport = atoi(row.Get("ExternalPort").c_str());
		int type = atoi(row.Get("Type").c_str());
		if (intport >= 1 && intport <= 65535 && extport >= 1 && extport <= 65535 && type >= 0 && type <= 2) {
			// Enable forwarding outputs for this network
			if (type == 0 || type == 2) {
				stringstream cmd;
				cmd << "-A " << chain << " -p tcp -m tcp --dport " << extport << " -m state --state NEW -j DNAT --to-destination " << c->address << ":" << intport;
				f.addRule(cmd.str(), "nat");
			}
			if (type == 1 || type == 2) {
				stringstream cmd;
				cmd << "-A " << chain << " -p udp --dport " << extport << " -j DNAT --to-destination " << c->address << ":" << intport;
				f.addRule(cmd.str(), "nat");
			}
		} else {
			printf("Invalid port forwarding rule with ID %s for machine %s\n", row.Get("ID").c_str(), c->name.c_str());
		}
	}

	return true;
}

bool firewall_add_machine_rules(FirewallState& f, const string& chain) {
	bool ret = true;
	auto m = GetNetworkMachines(f.net->device);
	for (auto& x : m) {
		if (!firewall_add_machine_rules(f, chain, x)) {
			ret = false;
		}
	}
	return ret;
}

/*
			stringstream rule;
			rule << "PREROUTING -i " << net->iface << " -p tcp -m tcp -m state --state NEW -j " << chain;

*/

bool firewall_add_rules(shared_ptr<Network>& net) {
	string chain = "DRIFTVM_FWD_" + net->device;
	std::transform(chain.begin(), chain.end(), chain.begin(), ::toupper);

	FirewallState f(net);
	f.addChain("DRIFTVM_FWD");
	f.addChain(chain);
	f.addChain(chain, "nat");

	f.addCheckOrAdd("FORWARD -j DRIFTVM_FWD");
	f.addCheckOrAdd(mprintf("DRIFTVM_FWD -j %s", chain.c_str()));

	{
		stringstream cmd;
		cmd << "-F " << chain;
		f.addRule(cmd.str());
		f.addRule(cmd.str(), "nat");
	}

	if (net->type == NetworkTypes::NT_ROUTED) {
		{
			// Enable forwarding inputs for this network
			stringstream cmd;
			cmd << "-A " << chain << " -i " << net->device << " -j ACCEPT";
			f.addRule(cmd.str());
		}
		{
			// Enable forwarding outputs for this network
			stringstream cmd;
			cmd << "-A " << chain << " -o " << net->device << " -j ACCEPT";
			f.addRule(cmd.str());
		}

		// Add postrouting MASQUERADE for this network
		f.addCheckOrAdd(GetPostRule(net).c_str(), "nat");

		// Add port forwarding prerouting for this network
		f.addCheckOrAdd(GetPortForwardingTCPRule(net, chain).c_str(), "nat");
		f.addCheckOrAdd(GetPortForwardingUDPRule(net, chain).c_str(), "nat");

		if (!firewall_add_machine_rules(f, chain)) {
			return false;
		}
	} else {
		{
			// Disable forwarding inputs for this network
			stringstream cmd;
			cmd << "-A " << chain << " -i " << net->device << " -j DROP";
			f.addRule(cmd.str());
		}
		{
			// Disable forwarding outputs for this network
			stringstream cmd;
			cmd << "-A " << chain << " -o " << net->device << " -j DROP";
			f.addRule(cmd.str());
		}

		// Remove postrouting MASQUERADE for this network
		f.addDeleteIfExists(GetPostRule(net).c_str(), "nat");

		// Delete port forwarding prerouting for this network
		f.addDeleteIfExists(GetPortForwardingTCPRule(net, chain).c_str(), "nat");
		f.addDeleteIfExists(GetPortForwardingUDPRule(net, chain).c_str(), "nat");
	}

	return f.apply();
}

bool firewall_flush_rules(shared_ptr<Network>& net) {
	string chain = "DRIFTVM_FWD_" + net->device;
	std::transform(chain.begin(), chain.end(), chain.begin(), ::toupper);

	if (!firewall_delete_if_exists(mprintf("DRIFTVM_FWD -j %s", chain.c_str()))) {
		return false;
	}

	{
		// Flush chain for this network
		stringstream cmd;
		cmd << "iptables -F " << chain;
		system(cmd.str().c_str());
	}

	{
		// Delete chain for this network
		stringstream cmd;
		cmd << "iptables -X " << chain;
		system(cmd.str().c_str());
	}


	// Delete port forwarding prerouting for this network
	if (!firewall_delete_if_exists(GetPortForwardingTCPRule(net, chain).c_str(), "nat")) {
		return false;
	}
	if (!firewall_delete_if_exists(GetPortForwardingUDPRule(net, chain).c_str(), "nat")) {
		return false;
	}

	{
		// Flush chain for this network
		stringstream cmd;
		cmd << "iptables -t nat -F " << chain;
		system(cmd.str().c_str());
	}

	{
		// Delete chain for this network
		stringstream cmd;
		cmd << "iptables -t nat -X " << chain;
		system(cmd.str().c_str());
	}

	// Remove postrouting for this network
	if (!firewall_delete_if_exists(GetPostRule(net).c_str(), "nat")) {
		return false;
	}

	return true;
}
