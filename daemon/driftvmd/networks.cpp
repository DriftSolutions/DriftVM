//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"
#ifndef WIN32
#include <sys/ioctl.h>
#include <net/if.h>
#include <linux/sockios.h>
#include <ifaddrs.h>
#endif

networkMap networks;

const char * charset = "abcdefghijklmnopqrstuvwxyz0123456789_";

inline bool _loadFromRow(const SC_Row& row, Network * n) {
	n->id = atoi(row.Get("ID").c_str());
	n->device = row.Get("Device");
	n->address = row.Get("IP");
	n->setNetmask(atoi(row.Get("Netmask").c_str()));
	n->net_type = (NetworkTypes)atoi(row.Get("Type").c_str());
	n->iface = row.Get("Interface");
	if (n->id > 0 && n->device.length() && n->address.length() && n->iface.length() && n->netmask_str.length() && strspn(n->device.c_str(), charset) == n->device.length()) {
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

bool GetNetwork(const string& devname, shared_ptr<Network>& net, bool use_cache) {
	AutoMutex(wdMutex);
	auto x = use_cache ? networks.find(devname) : networks.end();
	if (x != networks.end()) {
		net = x->second;
		return true;
	} else {
		MYSQL_RES * res = sql->Query(mprintf("SELECT * FROM `Networks` WHERE `Device`='%s'", sql->EscapeString(devname).c_str()));
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
		setError("Could not find a network with that name!");
		return false;
	}
}

bool GetNetworkInterfaces(vector<NetworkInterface>& ifaces) {
	ifaces.clear();
#ifdef WIN32
	return false;
#else
	struct ifaddrs * ifaddr = NULL;
	if (getifaddrs(&ifaddr) != 0) {
		return false;
	}

	char host[NI_MAXHOST];
	for (struct ifaddrs *ifa = ifaddr; ifa != NULL; ifa = ifa->ifa_next) {
		if (ifa->ifa_addr == NULL) { continue; }
		if (ifa->ifa_addr->sa_family != AF_INET) { continue; }
		memset(&host, 0, sizeof(host));
		if (getnameinfo(ifa->ifa_addr, sizeof(struct sockaddr_in), host, NI_MAXHOST, NULL, 0, NI_NUMERICHOST) != 0) { continue; }
		NetworkInterface n;
		n.device = ifa->ifa_name;
		n.ip = host;
		ifaces.push_back(n);
	}

	freeifaddrs(ifaddr);
	return true;
#endif
}

bool GetNetworkInterfaces(set<string>& ifaces) {
	ifaces.clear();
#ifdef WIN32
	return false;
#else
	struct ifaddrs * ifaddr = NULL;
	if (getifaddrs(&ifaddr) != 0) {
		return false;
	}

	for (struct ifaddrs *ifa = ifaddr; ifa != NULL; ifa = ifa->ifa_next) {
		if (ifa->ifa_addr == NULL) { continue; }
		if (ifa->ifa_addr->sa_family != AF_INET) { continue; }
		ifaces.insert(ifa->ifa_name);
	}

	freeifaddrs(ifaddr);
	return true;
#endif
}

#ifndef WIN32

inline bool set_if_addr(const char * ifname, const char * ip, const char * subnet_mask) {
	struct ifreq ifr;
	memset(&ifr, 0, sizeof(ifr));
	strncpy(ifr.ifr_name, ifname, IFNAMSIZ);

	int skfd;

	if ((skfd = socket(PF_INET, SOCK_DGRAM, IPPROTO_IP)) < 0) {
		setError("Error creating socket to set interface %s status: %s", ifname, strerror(errno));
		return false;
	}

	ifr.ifr_addr.sa_family = AF_INET;
	inet_pton(AF_INET, ip, ifr.ifr_addr.sa_data + 2);
	int res = ioctl(skfd, SIOCSIFADDR, &ifr);
	if (res < 0) {
		setError("Interface '%s': Error setting IP: %s", ifname, strerror(errno));
		close(skfd);
		return false;
	}

	inet_pton(AF_INET, subnet_mask, ifr.ifr_addr.sa_data + 2);
	res = ioctl(skfd, SIOCSIFNETMASK, &ifr);
	if (res < 0) {
		setError("Interface '%s': Error setting subnet mask: %s", ifname, strerror(errno));
		close(skfd);
		return false;
	}

	printf("Interface '%s': set IP to %s / %s.\n", ifname, ip, subnet_mask);
	close(skfd);
	return true;
}

inline bool set_if_status(const char * ifname, bool up) {
	struct ifreq ifr;
	memset(&ifr, 0, sizeof(ifr));
	strncpy(ifr.ifr_name, ifname, IFNAMSIZ);

	int skfd;

	if ((skfd = socket(AF_INET, SOCK_DGRAM, 0)) < 0) {
		setError("Error creating socket to set interface %s status: %s", ifname, strerror(errno));
		return false;
	}

	if (ioctl(skfd, SIOCGIFFLAGS, &ifr) < 0) {
		setError("Interface '%s': Error getting flags: %s", ifname, strerror(errno));
		close(skfd);
		return false;
	}

	if (up) {
		ifr.ifr_flags |= IFF_UP;
	} else {
		ifr.ifr_flags &= ~IFF_UP;
	}
	int res = ioctl(skfd, SIOCSIFFLAGS, &ifr);
	if (res < 0) {
		setError("Interface '%s': Error setting flags: %s", ifname, strerror(errno));
		close(skfd);
		return false;
	}

	printf("Interface '%s': flags set to %04X.\n", ifname, ifr.ifr_flags);
	close(skfd);
	return true;
}

bool ActivateNetwork(shared_ptr<Network>& net) {
	AutoMutex(wdMutex);
	int n = 0;
	bool ret = true;
	string ip;

	int fd = socket(AF_INET, SOCK_STREAM, 0);
	if (fd == -1) {
		setError("Error opening socket while creating bridge: %s", strerror(errno));
		goto error_end;
	}

	/* Create the bridge device */
	n = ioctl(fd, SIOCBRADDBR, net->device.c_str());
	if (n < 0 && errno != EEXIST) {
		setError("Error while creating bridge: %s", strerror(errno));
		goto error_end;
	}

	struct in_addr in;
	memset(&in, 0, sizeof(in));
	in.s_addr = inet_network(net->address.c_str());
	if (in.s_addr == -1) {
		setError("Error converting IP to number: %s", strerror(errno));
		goto error_end;
	}
	in.s_addr++;
	in.s_addr = htonl(in.s_addr);
	ip = inet_ntoa(in);
	printf("%s / %u => %s\n", net->address.c_str(), net->netmask_str.c_str(), ip.c_str());

	if (!set_if_addr(net->device.c_str(), ip.c_str(), net->netmask_str.c_str())) {
		goto error_end;
	}
	if (!set_if_status(net->device.c_str(), true)) {
		goto error_end;
	}
	if (!firewall_add_rules(net)) {
		setError("Error applying firewall rules!");
		goto error_end;
	}
	goto close_end;
error_end:
	ret = false;
	DestroyNetwork(net, false);
close_end:
	close(fd);
	net->status = ret;
	return ret;
}

bool DestroyNetwork(shared_ptr<Network>& net, bool delete_from_db) {
	AutoMutex(wdMutex);

	if (!set_if_status(net->device.c_str(), false)) {
		return false;
	}

	firewall_flush_rules(net);

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

	if (delete_from_db && !sql->NoResultQuery(mprintf("DELETE FROM `Networks` WHERE `ID`=%d", net->id))) {
		setError("Error deleting network from DB: %s", sql->GetErrorString().c_str());
		goto error_end;
	}

	goto close_end;
error_end:
	ret = false;
close_end:
	close(fd);
	return ret;
}

#else

bool ActivateNetwork(shared_ptr<Network>& net) { return true; }
bool DestroyNetwork(shared_ptr<Network>& net, bool delete_from_db) { return true; }

#endif

bool ActivateNetwork(const string& device) {
	AutoMutex(wdMutex);
	shared_ptr<Network> net;
	if (GetNetwork(device, net)) {
		return ActivateNetwork(net);
	}
	setError("Network with that device not found!");
	return false;
}

bool DestroyNetwork(const string& device, bool delete_from_db) {
	AutoMutex(wdMutex);
	shared_ptr<Network> net;
	if (GetNetwork(device, net)) {
		return DestroyNetwork(net, delete_from_db);
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
