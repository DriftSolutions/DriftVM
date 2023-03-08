//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"
#include <math.h>

machineMap machines;
extern const char * charset;

inline bool _loadFromRow(const SC_Row& row, Machine * n) {
	n->id = atoi(row.Get("ID").c_str());
	n->name = row.Get("Name");
	n->address = row.Get("IP");
	n->type = row.Get("Type");
	n->network = row.Get("Network");
	n->create_options = row.Get("CreateOptions");
	n->status = (MachineStatus)atoi(row.Get("Status").c_str());
	if (n->id > 0 && n->name.length() && n->type.length() && n->network.length() && strspn(n->name.c_str(), charset) == n->name.length()) {
		return true;
	}
	return false;
}

bool LoadMachinesFromDB() {
	MYSQL_RES * res = sql->Query("SELECT * FROM `Machines`");
	if (res == NULL) {
		return false;
	}

	AutoMutex(wdMutex);
	networks.clear();
	SC_Row row;
	while (sql->FetchRow(res, row)) {
		shared_ptr<Machine> n = make_shared<Machine>();
		if (_loadFromRow(row, n.get())) {
			machines[n->name] = n;
		}
	}

	return true;
}

bool GetMachine(const string& devname, shared_ptr<Machine>& net, bool use_cache) {
	AutoMutex(wdMutex);
	auto x = machines.find(devname);
	if (use_cache && x != machines.end()) {
		net = x->second;
		return true;
	} else {
		MYSQL_RES * res = sql->Query(mprintf("SELECT * FROM `Machines` WHERE `Name`='%s'", sql->EscapeString(devname).c_str()));
		SC_Row row;
		if (res != NULL && sql->FetchRow(res, row)) {
			shared_ptr<Machine> n = make_shared<Machine>();
			if (_loadFromRow(row, n.get())) {
				AutoMutex(wdMutex);
				machines[n->name] = net = n;
				sql->FreeResult(res);
				return true;
			}
		}
		sql->FreeResult(res);
		setError("Could not find a machine with that name!");
		return false;
	}
}

bool IsIPInUse(shared_ptr<Network>& net, const string& ip) {
	MYSQL_RES * res = sql->Query(mprintf("SELECT COUNT(*) AS `Count` FROM `Machines` WHERE `Network`='%s' AND `IP`='%s'", net->device.c_str(), ip.c_str()));
	if (res == NULL) {
		return false;
	}
	SC_Row row;
	bool ret = false;
	if (sql->FetchRow(res, row)) {
		ret = (atoi(row.Get("Count", "0").c_str()) > 0);
	}
	sql->FreeResult(res);
	return ret;
}

bool UpdateMachineIP(shared_ptr<Machine>& c) {
	return sql->NoResultQuery(mprintf("UPDATE `Machines` SET `IP`='%s' WHERE `ID`='%d'", c->address.c_str(), c->id));
}
bool UpdateMachineStatus(shared_ptr<Machine>& c) {
	return sql->NoResultQuery(mprintf("UPDATE `Machines` SET `Status`='%u',`LastError`='%s' WHERE `ID`='%d'", c->status, sql->EscapeString(getError()).c_str(), c->id));
}

bool CreateMachine(shared_ptr<Machine>& c) {
	AutoMutex(wdMutex);
	bool ret = true;
	string ip;
	bool found = false;

	unique_ptr<MachineDriver> d;
	if (!GetMachineDriver(c, d)) {
		setError("Error getting machine driver!");
		return false;
	}

	setError("Unknown error");
	shared_ptr<Network> net;
	if (!GetNetwork(c->network, net)) {
		return false;
	}
	if (net->netmask_int > 30) {
		setError("Subnet mask is too large!");
		return false;
	}	
	struct in_addr in;
	memset(&in, 0, sizeof(in));
	uint32 num_addresses = pow(2, 32 - net->netmask_int) - 3; // 2 unusable + the br address = 3
#ifdef WIN32
	int addr = -1;
#else
	auto addr = inet_network(net->address.c_str());
#endif
	if (addr == -1) {
		setError("Error converting IP to number: %s", strerror(errno));
		goto error_end;
	}
	addr++; // first usable IP
	addr++; // in use by the br interface
	found = false;
	for (uint32 i = 0; i < num_addresses; i++) {
		in.s_addr = htonl(addr++);
		ip = inet_ntoa(in);
		printf("%s / %u => %s\n", net->address.c_str(), net->netmask_str.c_str(), ip.c_str());
		if (!IsIPInUse(net, ip)) {
			found = true;
			break;
		}
	}	
	if (!found) {
		setError("There are no free IPs on your network!");
		goto error_end;
	}
	c->address = ip;
	UpdateMachineIP(c);

	if (!d->Create()) {
		goto error_end;
	}

	setError("");
	c->status = MachineStatus::MS_STOPPED;
	UpdateMachineStatus(c);
	goto close_end;
error_end:
	c->status = MachineStatus::MS_ERROR_CREATING;
	UpdateMachineStatus(c);
	ret = false;
close_end:
	return ret;
}

bool RemoveMachineFromDB(const string& name) {
	return sql->NoResultQuery(mprintf("DELETE FROM `Machines` WHERE `Name`='%s'", sql->EscapeString(name).c_str()));
}

void RemoveMachine(const string& name) {
	AutoMutex(wdMutex);
	auto x = machines.find(name);
	if (x != machines.end()) {
		machines.erase(x);
	}
}

void RemoveMachines() {
	AutoMutex(wdMutex);
	machines.clear();
}

/*
bool DeactivateNetwork(shared_ptr<Network>& net) {
	AutoMutex(wdMutex);

	if (!set_if_status(net->device.c_str(), false)) {
		return false;
	}

	firewall_del_rules(net);

	int n = 0;
	bool ret = true;
	int fd = socket(AF_INET, SOCK_STREAM, 0);
	if (fd == -1) {
		setError("Error opening socket while destroying bridge: %s", strerror(errno));
		goto error_end;
	}
	n = ioctl(fd, SIOCBRDELBR, net->device.c_str());
	if (n < 0) {
		setError("Error while destroying bridge: %s", strerror(errno));
		goto error_end;
	}
	goto close_end;
error_end:
	ret = false;
close_end:
	close(fd);
	return ret;
}

bool ActivateNetwork(const string& device) {
	AutoMutex(wdMutex);
	shared_ptr<Network> net;
	if (GetNetwork(device, net)) {
		return ActivateNetwork(net);
	}
	setError("Network with that device not found!");
	return false;
}

bool DeactivateNetwork(const string& device) {
	AutoMutex(wdMutex);
	shared_ptr<Network> net;
	if (GetNetwork(device, net)) {
		return DeactivateNetwork(net);
	}
	setError("Network with that device not found!");
	return false;
}

void RemoveNetwork(const string& device) {
	AutoMutex(wdMutex);
	auto x = networks.find(device);
	if (x != networks.end()) {
		networks.erase(x);
	}
}

void RemoveNetworks() {
	AutoMutex(wdMutex);
	networks.clear();
}
*/