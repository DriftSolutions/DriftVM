//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#pragma once
#ifndef DSL_STATIC
#define DSL_STATIC
#endif
#ifndef ENABLE_MYSQL
#define ENABLE_MYSQL
#endif
#ifndef DSL_NO_COMPAT
#define DSL_NO_COMPAT
#endif

#include <drift/dsl.h>
#include <memory>
#include <univalue.h>
#include "util.h"
#include "rpc/server.h"

#define DRIFTVMD_VERSION "0.0.1"

struct CONFIG_RPC {
	char bind_ip[128];
	uint16_t port;
	char user[128];
	char pass[128];
	uint16_t threads;
};

struct CONFIG_MYSQL {
	char host[128];
	uint16_t port;
	char user[128];
	char pass[128];
	char dbname[128];
};

struct CONFIG {
public:
	bool fShutdown = false;
#if !defined(WIN32)
	bool fFork = false;
#endif
	FILE * log_fp = NULL;
	char tmp_dir[MAX_PATH];

	CONFIG_MYSQL db;
	CONFIG_RPC rpc;
};
extern CONFIG config;
extern DSL_Mutex wdMutex;
extern DSL_Sockets * socks;
extern DB_MySQL * sql;

enum class NetworkTypes {
	NT_ROUTED,
	NT_ISOLATED
};

class Network {
private:
	uint8 _netmask_int = 0;
	string _netmask_str;
public:
	int id = 0;
	string device;
	bool status = false;

	string address;
	const uint8& netmask_int = _netmask_int;
	const string& netmask_str = _netmask_str;
	NetworkTypes type = NetworkTypes::NT_ROUTED;
	string iface; // the interface to listen for port forwards on

	void setNetmask(uint8 mask) {
		_netmask_int = mask;
		union {
			uint8 parts[4];
			uint32 full;
		};
		full = 0;
		uint32 cur = 0x80000000;
		for (uint32 i = 0; i < mask; i++) {
			full |= cur;
			cur >>= 1;
		}
		full = htonl(full);
		char buf[32];
		snprintf(buf, sizeof(buf), "%u.%u.%u.%u", parts[0], parts[1], parts[2], parts[3]);
		_netmask_str = buf;
	}
};
typedef map<string, shared_ptr<Network>> networkMap;
extern networkMap networks;

/* Network functions */
bool LoadNetworksFromDB();
bool GetNetwork(const string& devname, shared_ptr<Network>& net, bool use_cache = true);
void RemoveNetwork(const string& device);
void RemoveNetworks();

bool ActivateNetwork(shared_ptr<Network>& net);
bool ActivateNetwork(const string& device);

bool DeactivateNetwork(shared_ptr<Network>& net);
bool DeactivateNetwork(const string& device);

enum class MachineStatus {
	MS_STOPPED = 0,
	MS_CREATING = 1,
	MS_ERROR_CREATING = 2,
	MS_UPDATING = 3,
	MS_STARTING = 4,
	MS_STOPPING = 5,
	MS_DELETING = 6,
	MS_RUNNING = 100
};

class CreateOptionsLXC {
public:
	string path;
	string lxc_template;
	bool use_image = false;
	int image_size = 0;
	CreateOptionsLXC(const string& json) {
		UniValue obj(UniValue::VOBJ);
		if (!obj.read(json) || !obj.isObject()) {
			return;
		}
		if (obj.exists("path") && obj["path"].isStr()) {
			path = obj["path"].get_str();
		}
		if (obj.exists("lxc_template") && obj["lxc_template"].isStr()) {
			lxc_template = obj["lxc_template"].get_str();
		}
		if (obj.exists("use_image") && obj["use_image"].isBool()) {
			use_image = obj["use_image"].get_bool();
		}
		if (obj.exists("image_size") && obj["image_size"].isNum()) {
			image_size = obj["image_size"].get_int();
		}
	}

	bool IsValid() {
		if (path.length() == 0) { return false; }
		if (lxc_template.length() == 0) { return false; }
		if (use_image && image_size <= 0) {
			return false;
		}
		return true;
	}
};

class CreateOptionsKVM {
public:
	string path;
	string cdrom;
	string os_type;
	uint32 memory = 0;
	uint8 cpu_count = 0;
	uint32 disk_size = 0;
	CreateOptionsKVM(const string& json) {
		UniValue obj(UniValue::VOBJ);
		if (!obj.read(json) || !obj.isObject()) {
			return;
		}
		if (obj.exists("path") && obj["path"].isStr()) {
			path = obj["path"].get_str();
		}
		if (obj.exists("cdrom") && obj["cdrom"].isStr()) {
			cdrom = obj["cdrom"].get_str();
		}
		if (obj.exists("os_type") && obj["os_type"].isStr()) {
			os_type = obj["os_type"].get_str();
		}
		if (obj.exists("memory") && obj["memory"].isNum()) {
			memory = obj["memory"].get_int();
		}
		if (obj.exists("cpu_count") && obj["cpu_count"].isNum()) {
			cpu_count = obj["cpu_count"].get_int();
		}
		if (obj.exists("disk_size") && obj["disk_size"].isNum()) {
			disk_size = obj["disk_size"].get_int();
		}
	}

	bool IsValid() {
		if (path.length() == 0) { return false; }
		if (cdrom.length() == 0) { return false; }
		if (os_type.length() == 0) { return false; }
		if (memory < 512) { return false; }
		if (cpu_count == 0) { return false; }
		if (disk_size < 1) { return false; }
		return true;
	}
};

typedef map<string, string> extraMap;

class Machine {
private:
	extraMap extra;
public:
	int id = 0;
	string name;
	MachineStatus status = MachineStatus::MS_STOPPED;

	string type;
	string address;
	string network;
	string create_options;

	bool canDelete() {
		return (status == MachineStatus::MS_STOPPED || status == MachineStatus::MS_ERROR_CREATING);
	}

	void updateExtra(string k, string v) {
		AutoMutex(wdMutex);
		extra[k] = v;
	}
	string getExtra(const string& key, const string& def = "") {
		AutoMutex(wdMutex);
		auto x = extra.find(key);
		if (x != extra.end()) {
			return x->second;
		}
		return def;
	}
	void getExtra(extraMap& m) {
		AutoMutex(wdMutex);
		m = extra;
	}
};
typedef map<string, shared_ptr<Machine>> machineMap;
extern machineMap machines;

class MachineDriver {
protected:
	string getRandomPassword(size_t len);
public:
	shared_ptr<Machine> c;
	MachineDriver(shared_ptr<Machine>& pc) {
		c = pc;
	}
	virtual ~MachineDriver() {};

	virtual bool Create() = 0;
	virtual MachineStatus GetStatus(bool * success = NULL) = 0;
	virtual bool Start() = 0;
	virtual bool Stop() = 0;
	virtual bool Delete() = 0;

	virtual bool IsNormalStatus() {
		static set<MachineStatus> skip = { MachineStatus::MS_RUNNING, MachineStatus::MS_STARTING, MachineStatus::MS_STOPPING, MachineStatus::MS_STOPPED };
		return (skip.find(c->status) != skip.end());
	}
	virtual bool IsSpecialStatus() {
		static set<MachineStatus> skip = { MachineStatus::MS_CREATING, MachineStatus::MS_DELETING, MachineStatus::MS_ERROR_CREATING, MachineStatus::MS_UPDATING };
		return (skip.find(c->status) != skip.end());
	}
};

extern const string GUID_LXC;
extern const string GUID_KVM;
bool GetMachineDriver(/* in */ shared_ptr<Machine>& c, /* out */ unique_ptr<MachineDriver>& driver);
bool GetMachineDriverLXC(shared_ptr<Machine>& c, unique_ptr<MachineDriver>& driver);
bool GetMachineDriverKVM(shared_ptr<Machine>& c, unique_ptr<MachineDriver>& driver);

/* Machine functions */
bool LoadMachinesFromDB();
void UpdateMachineStatuses();
bool GetMachine(const string& devname, shared_ptr<Machine>& net, bool use_cache = true);
bool IsIPInUse(shared_ptr<Network>& net, const string& ip);

bool UpdateMachineIP(shared_ptr<Machine>& c);
bool UpdateMachineStatus(shared_ptr<Machine>& c);
bool UpdateMachineExtra(shared_ptr<Machine>& c);
bool GetMachineMAC(shared_ptr<Machine>& c, string& mac);
bool CreateMachine(shared_ptr<Machine>& c);

bool RemoveMachineFromDB(const string& name);
void RemoveMachine(const string& name);
void RemoveMachines();

void QueueMachineJob(MachineStatus func, const string& name);
bool IsJobQueued(const string& name);
void RunJobs();

bool firewall_add_rules(shared_ptr<Network>& net);
bool firewall_flush_rules(shared_ptr<Network>& net);

void driftvmd_printf(const char * fmt, ...);
#define printf driftvmd_printf
