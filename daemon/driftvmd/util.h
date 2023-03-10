//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#pragma once

void setError(const char * fmt, ...);
string getError();

string GetTempDirFile(string fn);
int GetNumCPUs();
string escapeshellarg(const string& str);

class NetworkInterface {
public:
	string device;
	string ip;
};

/* These are the machine's interfaces, not DriftVM interfaces */
bool GetNetworkInterfaces(vector<NetworkInterface>& ifaces);
bool GetNetworkInterfaces(set<string>& ifaces);
