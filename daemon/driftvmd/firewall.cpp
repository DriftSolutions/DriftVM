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

bool firewall_init() {
	quiet_system("iptables -N DRIFTVM_FWD");
	if (quiet_system("iptables --check FORWARD -j DRIFTVM_FWD")) {
		printf("Adding main FORWARD rule...\n");
		if (system("iptables -A FORWARD -j DRIFTVM_FWD") != 0) {
			printf("Error adding iptables rule!\n");
			return false;
		}
	}

	return true;
}

bool firewall_add_rules(shared_ptr<Network>& net) {
	string chain = "DRIFTVM_FWD_" + net->device;
	std::transform(chain.begin(), chain.end(), chain.begin(), ::toupper);

	{
		// Create chain for this network
		stringstream cmd;
		cmd << "iptables -F " << chain;
		return (system(cmd.str().c_str()) == 0);
	}
	return true;
}

bool firewall_del_rules(shared_ptr<Network>& net) {
	string chain = "DRIFTVM_FWD_" + net->device;
	std::transform(chain.begin(), chain.end(), chain.begin(), ::toupper);

	// Flush chain for this network
	stringstream cmd;
	cmd << "iptables -F " << chain;
	return (system(cmd.str().c_str()) == 0);
}
