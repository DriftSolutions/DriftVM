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
#include "util.h"
#include "rpc/server.h"

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
	string iface; // the interface to bridge to for routed bridges

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

bool LoadNetworksFromDB();
bool GetNetwork(const string& devname, shared_ptr<Network>& net);
void RemoveNetwork(const string& device);
void RemoveNetworks();

bool ActivateNetwork(shared_ptr<Network>& net);
bool ActivateNetwork(const string& device);

bool DeactivateNetwork(shared_ptr<Network>& net);
bool DeactivateNetwork(const string& device);

void driftvmd_printf(const char * fmt, ...);
#define printf driftvmd_printf
