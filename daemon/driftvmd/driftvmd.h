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
public:
	int id = 0;
	string device;
	string address;
	string netmask;
	NetworkTypes type = NetworkTypes::NT_ROUTED;
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
