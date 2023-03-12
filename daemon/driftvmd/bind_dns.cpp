//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"
#include <atomic>

atomic<time_t> lastBindUpdate{ 0 };

void UpdateBindNow() {
	lastBindUpdate = 0;
}

void UpdateBind() {
	if (time(NULL) - lastBindUpdate < 300) {
		return;
	}
	lastBindUpdate = time(NULL);

	string bind_ip = GetSetting("bind_ip");
	string pattern = GetSetting("bind_pattern");
	if (bind_ip.length() == 0 || pattern.length() == 0) {
		return;
	}

	map<string, string> add;
	vector<string> del;
	char buf[NI_MAXHOST] = { 0 };
	wdMutex.Lock();
	for (auto& c : machines) {
		if (c.second->isNormalStatus()) {
			if (c.second->bind_update > 0) {
				string h = str_replace(pattern, "%name%", c.second->name);
				if (c.second->bind_update == 2) {
					// Use network port forwarding listening IP
					shared_ptr<Network> net;
					if (GetNetwork(c.second->network, net)) {			
						NetworkInterface iface;
						if (GetNetworkInterface(net->iface, iface)) {
							add[h] = iface.ip;
						} else {
							printf("Could not get listening interface for %s for Bind DNS for machine %s, skipping...\n", c.second->network.c_str(), c.second->name.c_str());
						}
					} else {
						printf("Could not find network %s for Bind DNS for machine %s, skipping...\n", c.second->network.c_str(), c.second->name.c_str());
					}
				} else {
					// Use Machine  IP
					add[h] = c.second->address;
				}
			} else {
				del.push_back(str_replace(pattern, "%name%", c.second->name));
			}
		}
	}
	wdMutex.Release();
	if (add.size() == 0 && del.size() == 0) {
		return;
	}

	string fn = GetTempDirFile("nsupdate.txt");
	FILE * fp = fopen(fn.c_str(), "wb");
	if (fp == NULL) {
		printf("Error opening %d for write: %s\n", fn.c_str(), strerror(errno));
		return;
	}

	fprintf(fp, "server %s\n", bind_ip.c_str());
	for (auto& h : add) {
		fprintf(fp, "update add %s 600 IN A %s\n", h.first.c_str(), h.second.c_str());
	}
	for (auto& h : del) {
		fprintf(fp, "update delete %s 600 IN A\n", h.c_str());
	}
	fprintf(fp, "send\n");
	fclose(fp);

	stringstream cmd;
	cmd << "nsupdate " << escapeshellarg(fn);
	int n = system(cmd.str().c_str());
	if (n == 0) {
#ifndef DEBUG
		remove(fn.c_str());
#endif
	} else {
		printf("Error running '%s', exited with code %d. I have left %s in place for your reference.\n", cmd.str().c_str(), n, fn.c_str());
	}
}
