//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"

networkMap networks;

inline bool _loadFromRow(const SC_Row& row, Network * n) {
	n->id = atoi(row.Get("ID").c_str());
	n->device = row.Get("Device");
	n->address = row.Get("IP");
	n->netmask = row.Get("Netmask");
	n->type = (NetworkTypes)atoi(row.Get("Type").c_str());
	if (n->id > 0 && n->device.length() && n->address.length() && n->netmask.length()) {
		return true;
	}
	return false;
}

bool LoadNetworksFromDB() {
	MYSQL_RES * res = sql->Query("SELECT * FROM `Networks`");
	if (res == NULL) {
		return false;
	}

	AutoMutex(wdMutex);
	networks.clear();
	SC_Row row;
	while (sql->FetchRow(res, row)) {
		shared_ptr<Network> n = make_shared<Network>();
		if (_loadFromRow(row, n.get())) {
			networks[n->device] = n;
		}
	}

	return true;
}

bool GetNetwork(const string& devname, shared_ptr<Network>& net) {
	AutoMutex(wdMutex);
	auto x = networks.find(devname);
	if (x != networks.end()) {
		net = x->second;
		return true;
	} else {
		MYSQL_RES * res = sql->Query(mprintf("SELECT * FROM `Networks` WHERE `Device`='%s'", sql->EscapeString(devname)));
		SC_Row row;
		if (res != NULL && sql->FetchRow(res, row)) {
			shared_ptr<Network> n = make_shared<Network>();
			if (_loadFromRow(row, n.get())) {
				AutoMutex(wdMutex);
				networks[n->device] = net = n;
				sql->FreeResult(res);
				return true;
			}
		}
		sql->FreeResult(res);
		return false;
	}
}

bool ActivateNetwork(shared_ptr<Network>& net) {
	AutoMutex(wdMutex);
	return true;
}

bool ActivateNetwork(const string& device) {
	AutoMutex(wdMutex);
	auto x = networks.find(device);
	if (x != networks.end()) {
		return ActivateNetwork(x->second);
	}
	return false;
}


bool DeactivateNetwork(shared_ptr<Network>& net) {
	AutoMutex(wdMutex);
	return true;
}

bool DeactivateNetwork(const string& device) {
	AutoMutex(wdMutex);
	auto x = networks.find(device);
	if (x != networks.end()) {
		return DeactivateNetwork(x->second);
	}
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
