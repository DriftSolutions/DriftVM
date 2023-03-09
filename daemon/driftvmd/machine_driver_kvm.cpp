//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"

const string GUID_KVM = "{2E9F6C5A-34AE-4456-8CDF-EA5F0805A4B7}";

class MachineDriverKVM : public MachineDriver {
private:
	string disk_fn;
	bool updateDisplayNumber() {
		bool ret = false;
		stringstream cmd;
		cmd << "virsh vncdisplay " << c->name;
#ifdef DEBUG
		printf("Command: %s\n", cmd.str().c_str());
#endif
#ifndef WIN32
		FILE * fp = popen(cmd.str().c_str(), "r");
		if (fp != NULL) {
			char buf[256] = { 0 };
			if (fgets(buf, sizeof(buf) - 1, fp) != NULL) {
				strtrim(buf);
				c->updateExtra("VNC Display", buf);
			}
			pclose(fp);
		} else {
			setError("Error running command: %s", cmd.str().c_str());
		}
#endif
		return ret;
	}
public:
	MachineDriverKVM(shared_ptr<Machine>& pc) : MachineDriver(pc) {}
	virtual ~MachineDriverKVM() {};

	virtual bool Create() {
		CreateOptionsKVM opts(c->create_options);
		if (!opts.IsValid()) {
			setError("Invalid creation options for KVM!");
			return false;
		}

		shared_ptr<Network> net;
		if (!GetNetwork(c->network, net)) {
			return false;
		}

		string mac;
		if (!GetMachineMAC(c, mac)) {
			return false;
		}

		string diskfn = opts.path + c->name + ".hdimg";
		if (access(diskfn.c_str(), F_OK) == 0) {
			setError("Disk image %s already exists!", diskfn.c_str());
			return false;
		}

		/*
		{
			stringstream cmd;
			cmd << "fallocate -l " << opts.disk_size << "G " << escapeshellarg(diskfn);
			int n = system(cmd.str().c_str());
			if (n != 0) {
				setError("Error creating KVM container! (fallocate returned %d)", n);
				return false;
			}
		}
		*/

		string pass = getRandomPassword(16);

		stringstream cmd;
		//--virt-type kvm
		cmd << "virt-install --name " << c->name << " --network bridge=" << net->device << "";		
		cmd << " --memory " << opts.memory;
		cmd << " --vcpus " << int(opts.cpu_count);
		cmd << " --cdrom " << escapeshellarg(opts.cdrom);
		cmd << " --os-variant " << escapeshellarg(opts.os_type);
		cmd << " --disk size=" << opts.disk_size << ",path=" << diskfn;
		cmd << " --graphics vnc,listen=0.0.0.0,password=" << pass;
		cmd << " --force --noautoconsole --wait 0";
		printf("Command: %s\n", cmd.str().c_str());
		int n = system(cmd.str().c_str());
		if (n != 0) {
			setError("Error creating KVM container! (virt-install returned %d)", n);
			return false;
		}

		c->updateExtra("VNC Password", pass);
		updateDisplayNumber();
		UpdateMachineExtra(c);

		/*
		stringstream configfn;
		configfn << opts.path << c->name << PATH_SEPS << "config";
		FILE * fp = fopen(configfn.str().c_str(), "ab");
		if (fp == NULL) {
			setError("Error opening container configuration (%s): %s", configfn.str().c_str(), strerror(errno));
			return false;
		}

		fprintf(fp, "\n"); // make sure we're on a new line
		fprintf(fp, "lxc.net.0.type = veth\n");
		fprintf(fp, "lxc.net.0.flags = up\n");
		fprintf(fp, "lxc.net.0.link = %s\n", c->network.c_str());
		fprintf(fp, "lxc.net.0.name = eth0\n");
		fprintf(fp, "lxc.net.0.hwaddr = %s\n", mac.c_str());
		fprintf(fp, "lxc.net.0.ipv4.address = %s/%u\n", c->address.c_str(), net->netmask_int);
		fprintf(fp, "lxc.net.0.ipv4.gateway = auto\n");
		fclose(fp);
		*/

		c->status = MachineStatus::MS_RUNNING;
		return true;
	}

	virtual MachineStatus GetStatus(bool * success) {
		if (success) { *success = false; }

		stringstream cmd;
		cmd << "virsh list --all";
		MachineStatus ret = MachineStatus::MS_STOPPED;
		setError("");
#ifndef WIN32
		FILE * fp = popen(cmd.str().c_str(), "r");
		if (fp != NULL) {
			char buf[256] = { 0 };
			while (!feof(fp) && fgets(buf, sizeof(buf) - 1, fp) != NULL) {
				strtrim(buf);
				while (strstr(buf, "  ") != NULL) {
					str_replace(buf, sizeof(buf), "  ", " ");
				}
				StrTokenizer st(buf, ' ');
				if (st.NumTok() < 3) {
					continue;
				}
				if (stricmp(st.stdGetSingleTok(2).c_str(), c->name.c_str())) {
					continue;
				}
				string state = st.stdGetTok(3, st.NumTok());
				if (!stricmp(state.c_str(), "running")) {
					ret = MachineStatus::MS_RUNNING;
					if (success) { *success = true; }
				} else if (!stricmp(state.c_str(), "shut off")) {
					ret = MachineStatus::MS_STOPPED;
					if (success) { *success = true; }
				} else if (!stricmp(state.c_str(), "STARTING")) {
					ret = MachineStatus::MS_STARTING;
					if (success) { *success = true; }
				} else if (!stricmp(state.c_str(), "STOPPING") || !stricmp(state.c_str(), "ABORTING")) {
					ret = MachineStatus::MS_STOPPING;
					if (success) { *success = true; }
				} else {
					printf("Unrecognized KVM status: %s\n", state.c_str());
				}
			}
			pclose(fp);
		}
#endif

		setError("Error getting KVM container status!");
		return ret;
	}

	virtual bool Start() {
		stringstream cmd;
		cmd << "virsh start " << c->name;
		printf("Command: %s\n", cmd.str().c_str());
		int n = system(cmd.str().c_str());
		if (n != 0) {
			setError("Error stopping KVM container! (virsh returned %d)", n);
			return false;
		}

		if (updateDisplayNumber()) {
			UpdateMachineExtra(c);
		}

		setError("");
		c->status = MachineStatus::MS_RUNNING;
		return true;
	}

	virtual bool Stop() {
		stringstream cmd;
		cmd << "virsh shutdown " << c->name << " --mode=agent";
		printf("Command: %s\n", cmd.str().c_str());
		int n = system(cmd.str().c_str());
		if (n != 0) {
			setError("Error stopping KVM container! (virsh returned %d)", n);
			return false;
		}
		setError("");
		return true;
	}

	virtual bool Delete() {
		CreateOptionsKVM opts(c->create_options);
		if (!opts.IsValid()) {
			setError("Invalid creation options for KVM!");
			return false;
		}

		{
			stringstream cmd;
			cmd << "virsh destroy " << c->name;
			printf("Command: %s\n", cmd.str().c_str());
			system(cmd.str().c_str());
		}

		{
			stringstream cmd;
			cmd << "virsh undefine " << c->name;
			printf("Command: %s\n", cmd.str().c_str());
			int n = system(cmd.str().c_str());
			if (n != 0 && n != 256) {
				setError("Error deleting KVM container! (virsh returned %d)", n);
				return false;
			}
		}

		string diskfn = opts.path + c->name + ".hdimg";
		if (access(diskfn.c_str(), F_OK) == 0) {
			if (remove(diskfn.c_str()) != 0) {
				setError("Error deleting %s: %s", diskfn.c_str(), strerror(errno));
				return false;
			}
		}

		setError("");
		return true;
	}
};

bool GetMachineDriverKVM(shared_ptr<Machine>& c, unique_ptr<MachineDriver>& driver) {
	driver = make_unique<MachineDriverKVM>(c);
	return true;
}
