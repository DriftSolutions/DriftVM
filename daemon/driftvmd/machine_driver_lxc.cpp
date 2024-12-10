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
#include <sys/sendfile.h>
#include <sys/mount.h>
#endif

const string GUID_LXC = "{46634A47-CB89-4318-B98D-A691138C256B}";

bool driftvm_copy_file(const string& sfn, const string& tfn) {
#ifdef WIN32
	return (CopyFile(sfn.c_str(), tfn.c_str(), FALSE) != 0);
#else
	int fdi = open(sfn.c_str(), O_RDONLY);
	if (fdi == -1) {
		setError("Error opening %s for read: %s", sfn.c_str(), strerror(errno));
		return false;
	}

	int fdo = creat(tfn.c_str(), 0750);
	if (fdo == -1) {
		setError("Error opening %s for write: %s", tfn.c_str(), strerror(errno));
		close(fdi);
		return false;
	}

	struct stat st = { 0 };
	fstat(fdi, &st);
	bool ret = (sendfile(fdo, fdi, NULL, st.st_size) == st.st_size);
	if (!ret) {
		setError("Error copying %s to %s: %s", sfn.c_str(), tfn.c_str(), strerror(errno));
	}

	close(fdo);
	close(fdi);
	return ret;
#endif
}

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

		shared_ptr<Network> net;
		if (!GetNetwork(c->network, net)) {
			return false;
		}

		string mac;
		if (!GetMachineMAC(c, mac)) {
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

		if (opts.postinst.length()) {
			if (opts.use_image) {
				string rootdev = opts.path + c->name + "/rootdev";
				string mntdir = GetTempDirFile(c->name);
				if (access(mntdir.c_str(), 0) != 0) {
					dsl_mkdir(mntdir.c_str(), 0755);
				}

				stringstream cmd;
				cmd << "mount -o loop " << escapeshellarg(rootdev) << " " << escapeshellarg(mntdir);
				printf("Command: %s\n", cmd.str().c_str());
				int n = system(cmd.str().c_str());
				if (n != 0) {
					setError("Error mounting %s to %s: %s", rootdev.c_str(), mntdir.c_str(), strerror(errno));
					return false;
				}
/*
				n = mount(rootdev.c_str(), mntdir.c_str(), "ext4", 0, "loop");
				if (n == -1) {
					setError("Error mounting %s to %s: %s", rootdev.c_str(), mntdir.c_str(), strerror(errno));
					return false;
				}
*/

				string scriptfn = mntdir + "/root/postinst";
				if (!driftvm_copy_file(opts.postinst.c_str(), scriptfn.c_str())) {
#ifndef WIN32
					umount(mntdir.c_str());
#endif
					return false;
				}
#ifndef WIN32
				if (umount(mntdir.c_str()) == -1) {
					if (umount2(mntdir.c_str(), MNT_DETACH) == -1) {
						setError("Error unmounting %s from %s: %s", rootdev.c_str(), mntdir.c_str(), strerror(errno));
						return false;
					}
				}
#endif
			} else {
				string scriptfn = opts.path + c->name + "/rootfs/root/postinst";
				if (!driftvm_copy_file(opts.postinst.c_str(), scriptfn.c_str())) {
					return false;
				}
			}
			setError("");
			c->status = MachineStatus::MS_POST_INST;
			UpdateMachineStatus(c);

			stringstream cmd;
			cmd << "lxc-execute -n " << c->name << " -P " << escapeshellarg(opts.path) << " -- sh -c " << escapeshellarg("/root/postinst >/root/postinst.log 2>&1");
			printf("Command: %s\n", cmd.str().c_str());
			int n = system(cmd.str().c_str());
			if (n != 0) {
				setError("Error running post-install script! (lxc-execute returned %d)", n);
				return false;
			}
		}

		c->status = MachineStatus::MS_STOPPED;
		return true;
	}

	virtual MachineStatus GetStatus(bool * success) {
		if (success) { *success = false; }

		CreateOptionsLXC opts(c->create_options);
		if (!opts.IsValid()) {
			setError("Invalid creation options for LXC!");
			return MachineStatus::MS_STOPPED;
		}

		stringstream cmd;
		cmd << "lxc-info -Hsn " << c->name << " -P " << escapeshellarg(opts.path);
		MachineStatus ret = MachineStatus::MS_STOPPED;
		setError("");
#ifndef WIN32
		FILE * fp = popen(cmd.str().c_str(), "r");
		if (fp != NULL) {
			char buf[64] = { 0 };
			if (fgets(buf, sizeof(buf) - 1, fp) != NULL) {
				strtrim(buf);
				if (!stricmp(buf, "RUNNING")) {
					ret = MachineStatus::MS_RUNNING;
					if (success) { *success = true; }
				} else if (!stricmp(buf, "STOPPED")) {
					ret = MachineStatus::MS_STOPPED;
					if (success) { *success = true; }
				} else if (!stricmp(buf, "STARTING")) {
					ret = MachineStatus::MS_STARTING;
					if (success) { *success = true; }
				} else if (!stricmp(buf, "STOPPING") || !stricmp(buf, "ABORTING")) {
					ret = MachineStatus::MS_STOPPING;
					if (success) { *success = true; }
				} else {
					printf("Unrecognized LXC status: %s\n", buf);
				}
			}
			pclose(fp);
		}
#endif

		setError("Error getting LXC container status!");
		return ret;
	}

	virtual bool Start() {
		CreateOptionsLXC opts(c->create_options);
		if (!opts.IsValid()) {
			setError("Invalid creation options for LXC!");
			return false;
		}

		stringstream cmd;
		cmd << "lxc-start -d -n " << c->name << " -P " << escapeshellarg(opts.path);
		printf("Command: %s\n", cmd.str().c_str());
		int n = system(cmd.str().c_str());
		if (n != 0) {
			setError("Error starting LXC container! (lxc-start returned %d)", n);
			return false;
		}

		setError("");
		c->status = MachineStatus::MS_RUNNING;
		return true;
	}

	virtual bool Stop() {
		CreateOptionsLXC opts(c->create_options);
		if (!opts.IsValid()) {
			setError("Invalid creation options for LXC!");
			return false;
		}

		stringstream cmd;
		cmd << "lxc-stop -n " << c->name << " -P " << escapeshellarg(opts.path);
		printf("Command: %s\n", cmd.str().c_str());
		int n = system(cmd.str().c_str());
		if (n != 0) {
			setError("Error stopping LXC container! (lxc-stop returned %d)", n);
			return false;
		}

		setError("");
		c->status = MachineStatus::MS_STOPPED;
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

		setError("");
		return true;
	}
};

bool GetMachineDriverLXC(shared_ptr<Machine>& c, unique_ptr<MachineDriver>& driver) {
	driver = make_unique<MachineDriverLXC>(c);
	return true;
}

bool GetMachineDriver(/* in */ shared_ptr<Machine>& c, /* out */ unique_ptr<MachineDriver>& driver) {
	if (c->type == GUID_LXC) {
		return GetMachineDriverLXC(c, driver);
	} else if (c->type == GUID_KVM) {
		return GetMachineDriverKVM(c, driver);
	}
	return false;
}

string MachineDriver::getRandomPassword(size_t len) {
	static const char * charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	static const size_t charsetlen = strlen(charset);
	string ret;
	ret.reserve(len);
	size_t ind;
	while (len-- > 0) {
		dsl_fill_random_buffer((uint8 *)&ind, sizeof(ind));
		ret += charset[ind % charsetlen];
	}
	return ret;
}
