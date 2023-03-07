//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "server.h"
#include <atomic>

#include <event2/http.h>
#include <event2/buffer.h>
#include <event2/listener.h>
#include <event2/keyvalq_struct.h>
#include "base64/base64.h"

DSL_DEFINE_THREAD(ThreadRPC_Worker);

vector<D_SOCKET *> rpc_sockets;
atomic<int> rpc_worker_threads(0);
atomic<int> rpc_worker_threads_ready(0);
string rpc_user, rpc_pass;
RPC_Commands rpc_commands;
#define RPC_MAX_PAYLOAD_SIZE (64*1024)

void RPC_Request::SetError(const string errmsg, int jsonrpc_errnum, unsigned int phttp_code, string phttp_reason) {
	UniValue obj(UniValue::VOBJ);
	obj.pushKV("code", jsonrpc_errnum);
	obj.pushKV("message", errmsg);

	UniValue rep(UniValue::VOBJ);
	rep.pushKV("jsonrpc", "2.0");
	rep.pushKV("error", obj);
	rep.pushKV("id", json_request_id);

	reply = rep;
	http_code = phttp_code;
	http_reason = phttp_reason;
}

void RPC_Request::SetReply(const UniValue& result) {
	UniValue rep(UniValue::VOBJ);
	rep.pushKV("jsonrpc", "2.0");
	rep.pushKV("result", result);
	rep.pushKV("id", json_request_id);

	reply = rep;
	http_code = 200;
	http_reason = "OK";
}

void RPC_Request::SendReply(struct evhttp_request * req) {
	evbuffer * buf = evbuffer_new();
	string rep = reply.write();
	evbuffer_add(buf, rep.c_str(), rep.length());
	evhttp_send_reply(req, http_code, http_reason.c_str(), buf);
	evbuffer_free(buf);
}

/* for errors before JSON decoding */
inline void rpc_std_error(struct evhttp_request * req, string reason, int code = HTTP_BADREQUEST) {
	evbuffer * buf = evbuffer_new();
	evbuffer_add(buf, reason.c_str(), reason.length());
	evhttp_send_reply(req, code, (code == 401) ? "Unauthorized" : "Bad Request", buf);
	evbuffer_free(buf);
}

inline void rpc_req_404(struct evhttp_request * req) {
	evbuffer * buf = evbuffer_new();
	string reason = "Endpoint not found!";
	evbuffer_add(buf, reason.c_str(), reason.length());
	evhttp_send_reply(req, HTTP_NOTFOUND, "Not Found", buf);
	evbuffer_free(buf);
}

inline bool rpc_check_auth(const char * str) {
	if (strnicmp(str, "Basic ", 6) == 0) {
		char * user = (char *)malloc(strlen(str) * 2);
		base64::decoder dec;
		ptrdiff_t n = dec.decode(str + 6, strlen(str) - 6, user);
		if (n > 0) {
			user[n] = 0;
			char * pass = strchr(user, ':');
			if (pass) {
				*pass = 0;
				pass++;
				if (user == rpc_user && pass == rpc_pass) {
					free(user);
					return true;
				}
			}
		}
		free(user);
	}
	return false;
}

inline string rpc_type_name(UniValue::VType type) {
	switch (type) {
		case UniValue::VSTR:
			return "String";
			break;
		case UniValue::VNUM:
			return "Number";
			break;
		case UniValue::VBOOL:
			return "BOOL";
			break;
		case UniValue::VOBJ:
			return "Object";
			break;
		case UniValue::VARR:
			return "Array";
			break;
		case UniValue::VNULL:
			return "NULL";
			break;
		default:
			return "Unknown";
			break;
	}
}

inline bool rpc_check_params(RPC_Request& req, const RPC_Command& handler, UniValue& params) {
	if (params.size() < handler.min_parms || params.size() > handler.max_parms) {
		req.SetError("Invalid number of parameters!");
		return false;
	}
	for (auto x = handler.params.begin(); x != handler.params.end(); x++) {
		if (params.exists(x->name)) {
			if (params[x->name].type() != x->type) {
				req.SetError(mprintf("Parameter '%s' has an invalid type! Should be '%s' but got '%s'", x->name.c_str(), rpc_type_name(x->type).c_str(), rpc_type_name(params[x->name].type()).c_str()));
				return false;
			}
		} else if (x->required) {
			req.SetError(mprintf("Required parameter '%s' missing!", x->name.c_str()));
			return false;
		}
	}
	return true;
}

void rpc_req_handler(struct evhttp_request * evreq, void * ptr) {
	evkeyvalq * hin = evhttp_request_get_input_headers(evreq);
	evkeyvalq * hout = evhttp_request_get_output_headers(evreq);

	const char * auth = evhttp_find_header(hin, "Authorization");
	if (auth == NULL || !rpc_check_auth(auth)) {
		evhttp_add_header(hout, "WWW-Authenticate", "Basic realm=\"walletd RPC\"");
		rpc_std_error(evreq, "Invalid username and/or password!", 401);
		return;
	}

	if (evhttp_request_get_command(evreq) != EVHTTP_REQ_POST) {
		rpc_std_error(evreq, "This server only accepts POST requests!");
		return;
	}

	evbuffer * in = evhttp_request_get_input_buffer(evreq);
	size_t len = 0;
	if (in == NULL || (len = evbuffer_get_length(in)) > RPC_MAX_PAYLOAD_SIZE - 1) {
		rpc_std_error(evreq, "POST body too large!", HTTP_ENTITYTOOLARGE);
		return;
	}
	if (len == 0) {
		rpc_std_error(evreq, "Empty POST body!");
		return;
	}

#if defined(_DEBUG) && defined(_WIN32)
	DWORD ticks_parse;
	DWORD ticks = ticks_parse = GetTickCount();
#endif

	char * json = (char *)malloc(len + 1);
	if (evbuffer_copyout(in, json, len) != len) {
		rpc_std_error(evreq, "Internal Error");
		return;
	}
	json[len] = 0;
	UniValue post;
	if (!post.read(json, len)) {
		rpc_std_error(evreq, "Error decoding POST body!");
		free(json);
		return;
	}
	free(json);

#if defined(_DEBUG) && defined(_WIN32)
	ticks_parse = GetTickCount() - ticks_parse;
#endif

	if (!post.isObject() || !post.exists("method") || !post.exists("id") || !post.exists("params") || post["method"].isNull() || !post["method"].isStr()) {
		rpc_std_error(evreq, "Invalid JSON-RPC request!");
		return;
	}

	auto handler = rpc_commands.find(post["method"].get_str());
	if (handler == rpc_commands.end()) {
		rpc_req_404(evreq);
		return;
	}

	UniValue params;
	if (post["params"].isNull()) {
		UniValue np(UniValue::VOBJ);
		params = np;
	} else if (!post["params"].isObject() && !post["params"].isArray()) {
		rpc_std_error(evreq, "Invalid JSON-RPC request!");
		return;
	} else {
		params = post["params"];
	}
	evhttp_add_header(hout, "Content-Type", "application/json");

	RPC_Request req(params, post["id"]);
	if (rpc_check_params(req, handler->second, params)) {
		handler->second.func(req);
	}
	req.SendReply(evreq);

#if defined(_DEBUG) && defined(_WIN32)
	ticks = GetTickCount() - ticks;
	printf("RPC request took: %ums total, %ums parse\n", ticks, ticks_parse);
#endif
}

void CloseRPCPorts() {
	for (auto x = rpc_sockets.begin(); x != rpc_sockets.end(); x++) {
		socks->Close(*x);
	}
	rpc_sockets.clear();
}

bool BindRPCPorts() {

	int family = AF_INET;
	if (strchr(config.rpc.bind_ip, ':') != NULL) {
		//ok, we have an IPv6
		family = AF_INET6;
	}

	D_SOCKET * sock = socks->Create(family, SOCK_STREAM, IPPROTO_TCP);
	if (sock != NULL) {
		socks->SetReuseAddr(sock);
		if (socks->Bind(sock, config.rpc.port)) {
			if (socks->Listen(sock, 5)) {
				printf("[rpc] Listening on %s:%u!\n", config.rpc.bind_ip, config.rpc.port);
				socks->SetNonBlocking(sock);
				rpc_sockets.push_back(sock);
				return true;
			} else {
				printf("[rpc] Listen failed for %s:%u!\n", config.rpc.bind_ip, config.rpc.port);
			}
		} else {
			printf("[rpc] Bind failed for %s:%u (most likely port is in use already)...\n", config.rpc.bind_ip, config.rpc.port);
		}
	} else {
		printf("Error creating socket: %s\n", socks->GetLastErrorString());
	}

	socks->Close(sock);
	return false;
}

void rpc_stop(RPC_Request& req) {
	config.fShutdown = true;
}

void rpc_help(RPC_Request& req)
{
	if (req.params.exists("command")) {
		auto x = rpc_commands.find(req.params["command"].get_str());
		if (x != rpc_commands.end()) {
			ostringstream ret;
			ret << x->second.helptext << "\n";
			if (x->second.params.size()) {
				ret << "Parameters:\n";
				for (auto y = x->second.params.begin(); y != x->second.params.end(); y++) {
					ret << "\t" << y->name << " (" << rpc_type_name(y->type);
					if (y->required) {
						ret << ", Required";
					}
					ret << ")\n";
				}
				ret << "\n";
			}
			req.SetReply(ret.str());
		} else {
			req.SetReply(mprintf("Command '%s' not found.", req.params["command"].get_str().c_str()));
		}
	} else {
		ostringstream ret;
		ret << "Available commands:\n";
		for (auto x = rpc_commands.begin(); x != rpc_commands.end(); x++) {
			ret << x->second.name << "\n";
		}
		req.SetReply(ret.str());
	}
}

/*
class RPC_Command {
public:
	string category;
	string name;
	RPC_Command_Handler func = NULL;
	string helptext;
};
*/
const RPC_Command rpc_server_functions[] = {
	{ "core", "stop", &rpc_stop, {}, "Shuts down the daemon." },
	{ "core", "help", &rpc_help, { { "hash", UniValue::VSTR } }, "List available commands. Or provide 'command' parameter to view information on a specific command." },
};

void RPC_RegisterDVMFunctions(RPC_Commands& commands);

void RegisterRPCCommands() {
	for (size_t i = 0; i < (sizeof(rpc_server_functions) / sizeof(RPC_Command)); i++) {
		rpc_commands[rpc_server_functions[i].name] = rpc_server_functions[i];
	}

	RPC_RegisterDVMFunctions(rpc_commands);
}

bool RPC_Init() {
	rpc_user = config.rpc.user;
	rpc_pass = config.rpc.pass;
	if (rpc_user.length() == 0 || rpc_pass.length() == 0) {
		printf("You must specify both 'rpcuser' and 'rpcpass' to enable the RPC server!\n");
		return false;
	}

	RegisterRPCCommands();

	if (!BindRPCPorts()) {
		CloseRPCPorts();
		return false;
	}

	rpc_worker_threads = 0;
	rpc_worker_threads_ready = 0;
	int num = config.rpc.threads;
	if (num <= 0) {
		num = GetNumCPUs();
		if (num > 8) {
			num = 8;
		}
	}
	if (num < 1) {
		num = 1;
	} else if (num > 32) {
		num = 32;
	}

	printf("[rpc] Using %d worker threads...\n", num);
	char name[64];
	for (uint64_t i = 0; i < num; i++) {
		rpc_worker_threads++;
		snprintf(name, sizeof(name), "ThreadRPC_Worker " U64FMT "", i);
		DSL_StartThread(ThreadRPC_Worker, (void *)i, name);
	}

	time_t timeo = time(NULL) + 10;
	while (rpc_worker_threads_ready == 0 && time(NULL) < timeo) {
		safe_sleep(100, true);
	}

	if (rpc_worker_threads_ready == 0) {
		printf("No worker threads ready after 10 seconds!\n");
		return false;
	}

	return true;
}

void RPC_Quit() {
	config.fShutdown = true; /* just in case it's not already set */
	while (rpc_worker_threads) {
		safe_sleep(10, true);
	}
	CloseRPCPorts();
	rpc_commands.clear();
}

DSL_DEFINE_THREAD(ThreadRPC_Worker) {
	//event_base_get_num_events
	DSL_THREAD_INFO * tt = (DSL_THREAD_INFO *)lpData;
	int64 my_num = (int64)tt->parm;

	event_base * rpc_evbase = event_base_new();
	if (rpc_evbase != NULL) {
		evhttp * rpc_http = evhttp_new(rpc_evbase);
		if (rpc_http != NULL) {
			evhttp_set_allowed_methods(rpc_http, EVHTTP_REQ_GET | EVHTTP_REQ_POST);
#if LIBEVENT_VERSION_NUMBER >= 0x02010000
			evhttp_set_default_content_type(rpc_http, "text/plain");
#endif
			evhttp_set_timeout(rpc_http, 30);
			evhttp_set_max_body_size(rpc_http, RPC_MAX_PAYLOAD_SIZE - 1);
			evhttp_set_gencb(rpc_http, rpc_req_handler, NULL);

			vector<evhttp_bound_socket *> socks;
//			ksMutex.Lock();
			for (auto x = rpc_sockets.begin(); x != rpc_sockets.end(); x++) {
				const int flags = LEV_OPT_REUSEABLE | LEV_OPT_CLOSE_ON_EXEC;// | LEV_OPT_CLOSE_ON_FREE;
				evconnlistener * listener = evconnlistener_new(rpc_evbase, NULL, NULL, flags, 0, (*x)->sock);
				if (listener) {
					evhttp_bound_socket * tmp = evhttp_bind_listener(rpc_http, listener);
					if (tmp != NULL) {
						socks.push_back(tmp);
					}
				}
			}
//			ksMutex.Release();

			if (socks.size()) {
				rpc_worker_threads_ready++;
				while (!config.fShutdown) {
					size_t count = 0;
					while (event_base_loop(rpc_evbase, EVLOOP_NONBLOCK) == 0 && count++ < 25) {}
					safe_sleep(10, true);
				}
				rpc_worker_threads_ready--;

				//disable listening for new connections
				for (auto x = socks.begin(); x != socks.end(); x++) {
					evhttp_del_accept_socket(rpc_http, *x);
				}
				socks.clear();

				// Let existing connections finish up
#if LIBEVENT_VERSION_NUMBER >= 0x02010000
				// I know event_base_get_num_events() isn't exactly accurate but it's the best I see in the docs...
				time_t timeo = time(NULL) + 900;
				while (event_base_get_num_events(rpc_evbase, EVENT_BASE_COUNT_ADDED) > 0 && time(NULL) < timeo) {
#else
				time_t timeo = time(NULL) + 900;
				while (time(NULL) < timeo) {
#endif
					while (event_base_loop(rpc_evbase, EVLOOP_NONBLOCK) == 0) {}
					safe_sleep(10, true);
				}
			}

			evhttp_free(rpc_http);
		} else {
			printf("[rpc-" I64FMT "] Error initializing evhttp!\n", my_num);
		}
		event_base_free(rpc_evbase);
	} else {
		printf("[rpc-" I64FMT "] Error initializing ev_base!\n", my_num);
	}

	rpc_worker_threads--;
	DSL_THREAD_END
}
