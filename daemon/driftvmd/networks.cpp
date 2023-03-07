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
		setError("Not yet implemented");
		return false;
	}
}

bool ActivateNetwork(shared_ptr<Network>& net) {
	AutoMutex(wdMutex);
	int fd = socket(AF_INET, SOCK_STREAM, 0);
	if (fd == -1) {
		setError("Error opening socket while creating bridge: %s", strerror(errno));
		return false;
	}
#ifndef WIN32
	int n = ioctl(fd, SIOCBRADDBR, net->device.c_str());
	if (n < 0) {
		printf("n: %d, errno: %d\n", n, errno);
	}
#endif
	close(fd);
	return true;
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


bool DeactivateNetwork(shared_ptr<Network>& net) {
	AutoMutex(wdMutex);
	setError("Not yet implemented");
	return true;
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
