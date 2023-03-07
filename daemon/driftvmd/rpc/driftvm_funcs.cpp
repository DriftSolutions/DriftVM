//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "server.h"

void network_activate(RPC_Request& req) {
	string devname = req.params["device"].get_str();
	if (devname.length() == 0) {
		req.SetError("device is empty!");
		return;
	}
	if (ActivateNetwork(devname)) {
		req.SetReply(true);
	} else {
		req.SetError(getError());
	}
}

void network_deactivate(RPC_Request& req) {
	string devname = req.params["device"].get_str();
	if (devname.length() == 0) {
		req.SetError("device is empty!");
		return;
	}
	if (DeactivateNetwork(devname)) {
		req.SetReply(true);
	} else {
		req.SetError(getError());
	}
}

void network_delete(RPC_Request& req) {
	string devname = req.params["device"].get_str();
	if (devname.length() == 0) {
		req.SetError("device is empty!");
		return;
	}
	bool deactivate = true;
	if (req.params.exists("deactivate")) {
		deactivate = req.params["deactivate"].get_bool();
	}
	bool ret = true;
	if (deactivate) {
		ret = DeactivateNetwork(devname);
	}
	RemoveNetwork(devname);
	if (ret) {
		req.SetReply(true);
	} else {
		req.SetError(getError());
	}
}

const RPC_Command rpc_driftvm_functions[] = {	
	{ "network", "network_activate", &network_activate, { { "device", UniValue::VSTR, true } }, "Activate a network" },
	{ "network", "network_deactivate", &network_deactivate, { { "device", UniValue::VSTR, true } }, "Dectivate a network" },
	{ "network", "network_delete", &network_delete, { { "device", UniValue::VSTR, true }, { "deactivate", UniValue::VSTR, false } }, "Dectivate and delete a network" },
};

void RPC_RegisterDVMFunctions(RPC_Commands& commands) {
	for (size_t i = 0; i < (sizeof(rpc_driftvm_functions) / sizeof(RPC_Command)); i++) {
		commands[rpc_driftvm_functions[i].name] = rpc_driftvm_functions[i];
	}
}
