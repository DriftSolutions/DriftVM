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


bool firewall_init() {
	quiet_system("iptables -N DRIFTVM_FWD");
	if (!firewall_check_or_add("FORWARD -j DRIFTVM_FWD")) {
		return false;
	}

	return true;
}

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
bool firewall_add_machine_rules(shared_ptr<Network>& net, shared_ptr<Machine>& c, const string& chain, vector<string>& commands) {
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
				cmd << "iptables -t nat -A " << chain << " -p tcp -m tcp --dport " << extport << " -m state --state NEW -j DNAT --to-destination " << c->address << ":" << intport;
				commands.push_back(cmd.str());
			}
			if (type == 1 || type == 2) {
				stringstream cmd;
				cmd << "iptables -t nat -A " << chain << " -p udp --dport " << extport << " -j DNAT --to-destination " << c->address << ":" << intport;
				commands.push_back(cmd.str());
			}
		} else {
			printf("Invalid port forwarding rule with ID %s for machine %s\n", row.Get("ID").c_str(), c->name.c_str());
		}
	}

	return true;
}

bool firewall_add_machine_rules(shared_ptr<Network>& net, const string& chain, vector<string>& commands) {
	bool ret = true;
	auto m = GetNetworkMachines(net->device);
	for (auto& x : m) {
		if (!firewall_add_machine_rules(net, x, chain, commands)) {
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

	vector<string> commands;
	bool ret = true;

	{
		// Create chain for this network
		stringstream cmd;
		cmd << "iptables -N " << chain;
		quiet_system(cmd.str());
	}

	if (!firewall_check_or_add(mprintf("DRIFTVM_FWD -j %s", chain.c_str()))) {
		return false;
	}

	printf("[%s] %u %s\n", net->device.c_str(), net->type, net->iface.c_str());
	if (net->type == NetworkTypes::NT_ROUTED) {
		{
			// Enable forwarding inputs for this network
			stringstream cmd;
			cmd << "iptables -A " << chain << " -i " << net->device << " -j ACCEPT";
			commands.push_back(cmd.str());
		}
		{
			// Enable forwarding outputs for this network
			stringstream cmd;
			cmd << "iptables -A " << chain << " -o " << net->device << " -j ACCEPT";
			commands.push_back(cmd.str());
		}

		// Add postrouting for this network
		if (!firewall_check_or_add(GetPostRule(net).c_str(), "nat")) {
			ret = false;
		}

		{
			// Create chain for this network for port forwarding
			stringstream cmd;
			cmd << "iptables -t nat -N " << chain;
			quiet_system(cmd.str());
		}

		// Add port forwarding prerouting for this network
		if (!firewall_check_or_add(GetPortForwardingTCPRule(net, chain).c_str(), "nat")) {
			ret = false;
		}
		if (!firewall_check_or_add(GetPortForwardingUDPRule(net, chain).c_str(), "nat")) {
			ret = false;
		}

		if (!firewall_add_machine_rules(net, chain, commands)) {
			ret = false;
		}
	} else {
		{
			// Disable forwarding inputs for this network
			stringstream cmd;
			cmd << "iptables -A " << chain << " -i " << net->device << " -j DROP";
			commands.push_back(cmd.str());
		}
		{
			// Disable forwarding outputs for this network
			stringstream cmd;
			cmd << "iptables -A " << chain << " -o " << net->device << " -j DROP";
			commands.push_back(cmd.str());
		}

		// Remove postrouting for this network
		if (!firewall_delete_if_exists(GetPostRule(net).c_str(), "nat")) {
			ret = false;
		}

		{
			// Flush chain for this network for port forwarding
			stringstream cmd;
			cmd << "iptables -t nat -F " << chain;
			quiet_system(cmd.str());
		}

		// Add port forwarding prerouting for this network
		if (!firewall_delete_if_exists(GetPortForwardingTCPRule(net, chain).c_str(), "nat")) {
			ret = false;
		}
		if (!firewall_delete_if_exists(GetPortForwardingUDPRule(net, chain).c_str(), "nat")) {
			ret = false;
		}
	}

	for (auto& cmd : commands) {
#ifdef DEBUG
		printf("Command: %s\n", cmd.c_str());
#endif
		int n = system(cmd.c_str());
		if (n != 0) { // Allow the add chain to return 256 because it already exists
			setError("Error adding firewall rule: %s, return value: %d", cmd.c_str(), n);
			ret = false;
		}
	}

	return ret;
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
