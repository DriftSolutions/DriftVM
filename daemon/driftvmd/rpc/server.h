//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#ifndef __DRIFTVMD_RPC_SERVER__
#define __DRIFTVMD_RPC_SERVER__

#include "../driftvmd.h"
#include <univalue.h>
#include <initializer_list>

bool RPC_Init();
void RPC_Quit();

class RPC_Request {
private:
	unsigned int http_code = 400;
	string http_reason = "OK";
	UniValue json_request_id;
	UniValue _params;
	UniValue reply;
public:
	RPC_Request(const UniValue& pparams, const UniValue& pid) {
		json_request_id = pid;
		_params = pparams;
	}

	const UniValue& params = _params;

	void SetError(const string msg, int jsonrpc_errnum = -1, unsigned int http_code = 400, string http_reason = "Bad Request");
	void SetReply(const UniValue& preply);

	void SendReply(struct evhttp_request * req);
};

typedef void(*RPC_Command_Handler)(RPC_Request& req);

class RPC_Command_Params {
public:
	string name;
	UniValue::VType type = UniValue::VSTR;
	bool required = false;
};

class RPC_Command {
private:
	void updateFields() {
		min_parms = max_parms = 0;
		for (auto x = params.begin(); x != params.end(); x++) {
			max_parms++;
			if (x->required) {
				min_parms++;
			}
		}
	}
public:
	string category;
	string name;
	RPC_Command_Handler func = NULL;
	vector<RPC_Command_Params> params;
	string helptext;
	int min_parms = 0;
	int max_parms = 0;

	RPC_Command() {
	}

	RPC_Command(string pcategory, string pname, RPC_Command_Handler pfunc, std::initializer_list<RPC_Command_Params> parms, string phelptext) {
		category = pcategory;
		name = pname;
		func = pfunc;
		helptext = phelptext;
		for (auto x = parms.begin(); x != parms.end(); x++) {
			params.push_back(*x);
		}
		updateFields();
	}
};

typedef map<string, RPC_Command> RPC_Commands;

#endif
