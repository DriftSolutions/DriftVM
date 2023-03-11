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
	string extra = row.Get("Extra");
	if (extra.length()) {
		UniValue obj(UniValue::VOBJ);
		if (obj.read(extra) && obj.isObject()) {
			map<string, UniValue> kv;
			obj.getObjMap(kv);
			for (const auto& x : kv) {
				if (x.second.isStr()) {
					n->updateExtra(x.first, x.second.get_str());
				}
			}
		}
	}
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
	machines.clear();
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
	auto x = use_cache ? machines.find(devname) : machines.end();
	if (x != machines.end()) {
		net = x->second;
		return true;
	} else {
		MYSQL_RES * res = sql->Query(mprintf("SELECT * FROM `Machines` WHERE `Name`='%s'", sql->EscapeString(devname).c_str()));
		SC_Row row;
		if (res != NULL && sql->FetchRow(res, row)) {
			shared_ptr<Machine> n = make_shared<Machine>();
			if (_loadFromRow(row, n.get())) {
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
	AutoMutex(wdMutex);
	return sql->NoResultQuery(mprintf("UPDATE `Machines` SET `IP`='%s' WHERE `ID`='%d'", c->address.c_str(), c->id));
}
bool UpdateMachineStatus(shared_ptr<Machine>& c) {
	AutoMutex(wdMutex);
	return sql->NoResultQuery(mprintf("UPDATE `Machines` SET `Status`='%u',`LastError`='%s' WHERE `ID`='%d'", c->status, sql->EscapeString(getError()).c_str(), c->id));
}
bool UpdateMachineExtra(shared_ptr<Machine>& c) {
	AutoMutex(wdMutex);
	UniValue obj(UniValue::VOBJ);
	extraMap m;
	c->getExtra(m);
	for (auto& x : m) {
		obj.pushKV(x.first, x.second);
	}
	return sql->NoResultQuery(mprintf("UPDATE `Machines` SET `Extra`='%s' WHERE `ID`='%d'", obj.write().c_str(), c->id));
}

#pragma pack(1)
struct MACHINE_MAC {
	uint8 parts[6];
};
#pragma pack()

// First 2 bytes are the first 2 bytes of the sha256 hash of the network name, the remaining 4 bytes are the IP address
bool GetMachineMAC(shared_ptr<Machine>& c, string& mac) {
	if (c->address.length() == 0) {
		setError("Machine does not have an IP set!");
		return false;
	}
	StrTokenizer st((char *)c->address.c_str(), '.');
	if (st.NumTok() != 4) {
		setError("Machine has a malformed IP!");
		return false;
	}

	shared_ptr<Network> net;
	if (!GetNetwork(c->network, net)) {
		return false;
	}

	MACHINE_MAC m;
	memset(&m, 0, sizeof(m));
	for (int i = 1; i <= 4; i++) {
		m.parts[i + 1] = atoul(st.stdGetSingleTok(i).c_str());
	}

	char hash[32];
	if (!hashdata("sha256", (uint8 *)net->device.c_str(), net->device.length(), hash, sizeof(hash), true)) {
		setError("Error hashing network name!");
		return false;
	}
	m.parts[0] = hash[0];
	m.parts[1] = hash[1];

	mac = mprintf("%02x:%02x:%02x:%02x:%02x:%02x", m.parts[0], m.parts[1], m.parts[2], m.parts[3], m.parts[4], m.parts[5]);
	return true;
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

	wdMutex.Release();
	if (!d->Create()) {
		wdMutex.Lock();
		goto error_end;
	}
	wdMutex.Lock();

	setError("");
	UpdateMachineStatus(c);
	goto close_end;
error_end:
	c->status = MachineStatus::MS_ERROR_CREATING;
	UpdateMachineStatus(c);
	ret = false;
close_end:
	return ret;
}

bool RemoveMachineFromDB(int id) {
	sql->NoResultQuery(mprintf("DELETE FROM `PortForwards` WHERE `MachineID`=%d", id));
	return sql->NoResultQuery(mprintf("DELETE FROM `Machines` WHERE `ID`=%d", id));
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

void UpdateMachineStatuses() {
	machineMap m;
	{
		AutoMutex(wdMutex);
		m = machines;
	}

	set< MachineStatus> skip = { MachineStatus::MS_CREATING, MachineStatus::MS_DELETING, MachineStatus::MS_ERROR_CREATING, MachineStatus::MS_UPDATING };

	for (auto& c : m) {
		if (IsJobQueued(c.second->name)) {
			continue;
		}
		unique_ptr<MachineDriver> d;
		if (GetMachineDriver(c.second, d)) {
			if (d->IsSpecialStatus()) {
				continue;
			}
			bool suc = false;
			MachineStatus s = d->GetStatus(&suc);
			if (suc) {
				if (s != c.second->status) {
					AutoMutex(wdMutex);
					printf("Machine %s is in state %u, updating...\n", c.second->name.c_str(), s);
					c.second->status = s;
					UpdateMachineStatus(c.second);
				}
			}
		}
	}
}
