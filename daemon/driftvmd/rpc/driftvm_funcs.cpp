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

void network_firewall_apply(RPC_Request& req) {
	string devname = req.params["device"].get_str();
	if (devname.length() == 0) {
		req.SetError("device is empty!");
		return;
	}
	shared_ptr<Network> net;
	if (!GetNetwork(devname, net, false)) {
		req.SetError(getError());
		return;
	}
	if (firewall_add_rules(net)) {
		req.SetReply(true);
	} else {
		req.SetError(getError());
	}
}

void network_firewall_flush(RPC_Request& req) {
	string devname = req.params["device"].get_str();
	if (devname.length() == 0) {
		req.SetError("device is empty!");
		return;
	}
	shared_ptr<Network> net;
	if (!GetNetwork(devname, net, false)) {
		req.SetError(getError());
		return;
	}
	if (firewall_flush_rules(net)) {
		req.SetReply(true);
	} else {
		req.SetError(getError());
	}
}

void network_destroy(RPC_Request& req) {
	string devname = req.params["device"].get_str();
	if (devname.length() == 0) {
		req.SetError("device is empty!");
		return;
	}
	if (DestroyNetwork(devname, true)) {
		req.SetReply(true);
	} else {
		req.SetError(getError());
	}
}

void machine_create(RPC_Request& req) {
	string name = req.params["name"].get_str();
	if (name.length() == 0) {
		req.SetError("name is empty!");
		return;
	}
	QueueMachineJob(MachineStatus::MS_CREATING, name);
	req.SetReply(true);
}

void machine_start(RPC_Request& req) {
	string name = req.params["name"].get_str();
	if (name.length() == 0) {
		req.SetError("name is empty!");
		return;
	}

	shared_ptr<Machine> c;
	if (GetMachine(name, c, false)) {
		if (c->status == MachineStatus::MS_STOPPED) {
			c->status = MachineStatus::MS_STARTING;
			setError("");
			UpdateMachineStatus(c);
			QueueMachineJob(MachineStatus::MS_RUNNING, name);
			req.SetReply(true);
		} else {
			req.SetError("Machine is not in a valid state for starting!");
		}
	} else {
		req.SetError("I could not find that machine!");
	}
}

void machine_stop(RPC_Request& req) {
	string name = req.params["name"].get_str();
	if (name.length() == 0) {
		req.SetError("name is empty!");
		return;
	}
	shared_ptr<Machine> c;
	if (GetMachine(name, c, false)) {
		if (c->status == MachineStatus::MS_RUNNING) {
			c->status = MachineStatus::MS_STOPPING;
			setError("");
			UpdateMachineStatus(c);
			QueueMachineJob(MachineStatus::MS_STOPPED, name);
			req.SetReply(true);
		} else {
			req.SetError("Machine is not in a valid state for stopping!");
		}
	} else {
		req.SetError("I could not find that machine!");
	}
}

void machine_refresh(RPC_Request& req) {
	string name = req.params["name"].get_str();
	if (name.length() == 0) {
		req.SetError("name is empty!");
		return;
	}
	shared_ptr<Machine> c,old;
	GetMachine(name, old);
	if (GetMachine(name, c, false)) {
		if (old.get() == nullptr || old->bind_update != c->bind_update) {
			UpdateBindNow();
		}
		req.SetReply(true);
	} else {
		req.SetError("I could not find that machine!");
	}
}

void machine_delete(RPC_Request& req) {
	string name = req.params["name"].get_str();
	if (name.length() == 0) {
		req.SetError("name is empty!");
		return;
	}

	shared_ptr<Machine> c;
	if (GetMachine(name, c, false)) {
		if (c->canDelete()) {
			c->status = MachineStatus::MS_DELETING;
			setError("");
			UpdateMachineStatus(c);
			QueueMachineJob(MachineStatus::MS_DELETING, name);
			req.SetReply(true);
		} else {
			req.SetError("Machine is not in a valid state for deleting!");
		}
	} else {
		req.SetError("I could not find that machine!");
	}
}

void getinfo(RPC_Request& req) {
	UniValue obj(UniValue::VOBJ);
	obj.pushKV("version", DRIFTVMD_VERSION);
	req.SetReply(obj);
}

const RPC_Command rpc_driftvm_functions[] = {	
	{ "misc", "getinfo", &getinfo, {}, "Get information about the driftvmd node" },

	{ "network", "network_activate", &network_activate, { { "device", UniValue::VSTR, true } }, "Activate a network" },
	{ "network", "network_destroy", &network_destroy, { { "device", UniValue::VSTR, true } }, "Destroy a network" },
	{ "network", "network_firewall_apply", &network_firewall_apply, { { "device", UniValue::VSTR, true } }, "Apply firewall rules for a network" },
	{ "network", "network_firewall_flush", &network_firewall_flush, { { "device", UniValue::VSTR, true } }, "Flush firewall rules for a network" },


	{ "machines", "machine_create", &machine_create, { { "name", UniValue::VSTR, true } }, "Create a new machine" },
	{ "machines", "machine_start", &machine_start, { { "name", UniValue::VSTR, true } }, "Start a machine" },
	{ "machines", "machine_stop", &machine_stop, { { "name", UniValue::VSTR, true } }, "Stop a machine" },
	{ "machines", "machine_refresh", &machine_refresh, { { "name", UniValue::VSTR, true } }, "Make driftvmd reload the machine from the DB instead" },
	{ "machines", "machine_delete", &machine_delete, { { "name", UniValue::VSTR, true } }, "Delete a machine" },
};

void RPC_RegisterDVMFunctions(RPC_Commands& commands) {
	for (size_t i = 0; i < (sizeof(rpc_driftvm_functions) / sizeof(RPC_Command)); i++) {
		commands[rpc_driftvm_functions[i].name] = rpc_driftvm_functions[i];
	}
}
