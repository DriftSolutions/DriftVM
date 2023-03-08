//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"

const string GUID_LXC = "{46634A47-CB89-4318-B98D-A691138C256B}";

class MachineDriverLXC: public MachineDriver {
public:
	MachineDriverLXC(shared_ptr<Machine>& pc) : MachineDriver(pc) {}
	virtual ~MachineDriverLXC() {};

	virtual bool Create() {
		CreateOptionsLXC opts(c->create_options);
		if (!opts.IsValid()) {
			setError("Invalid creation options for LXC!");
			return false;
		}

		stringstream cmd;
		cmd << "lxc-create -n " << c->name << " -t " << escapeshellarg(opts.lxc_template) << " -P " << escapeshellarg(opts.path);
		if (opts.use_image) {
			cmd << " -B loop --fstype=ext4 --fssize=" << opts.image_size << "G";
		}
		printf("Command: %s\n", cmd.str().c_str());
		int n = system(cmd.str().c_str());
		if (n != 0) {
			setError("Error creating LXC container! (lxc-create returned %d)", n);
			return false;
		}

		return true;
	}

	virtual bool Start() {
		return true;
	}

	virtual bool Stop() {
		return true;
	}

	virtual bool Delete() {
		CreateOptionsLXC opts(c->create_options);
		if (!opts.IsValid()) {
			setError("Invalid creation options for LXC!");
			return false;
		}

		stringstream cmd;
		string path = opts.path + c->name;
		cmd << "rm -fR " << escapeshellarg(path);
		printf("Command: %s\n", cmd.str().c_str());
		int n = system(cmd.str().c_str());
		if (n != 0) {
			setError("Error deleting LXC container! (rm returned %d)", n);
			return false;
		}
		return true;
	}
};

bool GetMachineDriver(/* in */ shared_ptr<Machine>& c, /* out */ unique_ptr<MachineDriver>& driver) {
	if (c->type == GUID_LXC) {
		driver = make_unique<MachineDriverLXC>(c);
		return true;
	}
	return false;
}
