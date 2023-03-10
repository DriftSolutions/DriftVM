//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"
#include <stdarg.h>

#ifdef DEBUG
#pragma comment(lib, "event_d.lib")
#else
#pragma comment(lib, "event.lib")
#endif

CONFIG config;
DSL_Mutex wdMutex;
DSL_Sockets * socks = NULL;
DB_MySQL * sql = NULL;

bool LoadConfig() {
	memset(&config, 0, sizeof(config));

	sstrcpy(config.tmp_dir, "." PATH_SEPS "tmp");
	sstrcpy(config.rpc.bind_ip, "127.0.0.1");
	config.rpc.port = 28401;

	Universal_Config2 cfg;
	if (!cfg.LoadConfigFromFile("driftvmd.conf")) {
		printf("Error loading driftvmd.conf!\n");
		return false;
	}

	ConfigSection * sec = cfg.GetSection(NULL, "Main");
	if (sec != NULL) {
		if (cfg.SectionHasValue(sec, "TmpDir")) {
			sstrcpy(config.tmp_dir, cfg.GetSectionValue(sec, "TmpDir").AsString().c_str());
			if (config.tmp_dir[strlen(config.tmp_dir) - 1] == PATH_SEP) {
				config.tmp_dir[strlen(config.tmp_dir) - 1] = 0;
			}
		}
#if !defined(WIN32)
		if (cfg.SectionHasValue(sec, "Daemon")) {
			config.fFork = cfg.GetSectionValue(sec, "Daemon").AsBool();
		}
#endif
	}

	sec = cfg.GetSection(NULL, "RPC");
	if (sec != NULL) {
		if (cfg.SectionHasValue(sec, "Port")) {
			ConfigValue val = cfg.GetSectionValue(sec, "Port");
			if (val.AsInt() > 0 && val.AsInt() < 65536) {
				config.rpc.port = (uint16)val.AsInt();
			}
		}
		if (cfg.SectionHasValue(sec, "Threads")) {
			ConfigValue val = cfg.GetSectionValue(sec, "Threads");
			if (val.AsInt() >= 0 && val.AsInt() <= 32) {
				config.rpc.threads = (uint16)val.AsInt();
			}
		}
		if (cfg.SectionHasValue(sec, "BindIP")) {
			sstrcpy(config.rpc.bind_ip, cfg.GetSectionValue(sec, "BindIP").AsString().c_str());
		}
		sstrcpy(config.rpc.user, cfg.GetSectionValue(sec, "User").AsString().c_str());
		sstrcpy(config.rpc.pass, cfg.GetSectionValue(sec, "Pass").AsString().c_str());
	}

	sec = cfg.GetSection(NULL, "Database");
	if (sec != NULL) {
		sstrcpy(config.db.host, cfg.GetSectionValue(sec, "Host").AsString().c_str());
		sstrcpy(config.db.user, cfg.GetSectionValue(sec, "User").AsString().c_str());
		sstrcpy(config.db.pass, cfg.GetSectionValue(sec, "Pass").AsString().c_str());
		sstrcpy(config.db.dbname, cfg.GetSectionValue(sec, "Database").AsString().c_str());
		if (cfg.SectionHasValue(sec, "Port")) {
			ConfigValue val = cfg.GetSectionValue(sec, "Port");
			if (val.AsInt() > 0 && val.AsInt() < 65536) {
				config.db.port = (uint16)val.AsInt();
			}
		}
	}

	return true;
}

#if defined(_WIN32)
/**
* Handles Ctrl+C, hitting the Close button, etc., on Windows
*/
BOOL WINAPI HandlerRoutine(DWORD dwCtrlType) {
	//dwCtrlType == CTRL_CLOSE_EVENT ||
	if (dwCtrlType == CTRL_CLOSE_EVENT || dwCtrlType == CTRL_C_EVENT || dwCtrlType == CTRL_BREAK_EVENT) {
		if (!config.fShutdown) {
			if (dwCtrlType != CTRL_CLOSE_EVENT) {
				printf("Caught Ctrl+C (or similar), shutting down...\n");
			}
			config.fShutdown = true;
		}
	}
	return TRUE;
}
#else

/**
* Catches Ctrl+C, etc. on Linux/Unix
*/
static void catch_sigint(int signo) {
	if (!config.fShutdown) {
		printf("Caught SIGINT, shutting down...\n");
		config.fShutdown = true;
	}
}

/**
* Catches SIGUSR1 on Linux/Unix
*/
/*
static void catch_sigusr1(int signo) {
	if (!config.cli_shutdown) {
		ib_printf(_("Caught SIGUSR1, restarting bot...\n"));
		config.shutdown_reboot = true;
		config.cli_shutdown = true;
	}
}
*/

#endif

void our_cleanup() {
	AutoMutex(wdMutex);
	RemoveMachines();
	RemoveNetworks();
	if (sql != NULL) {
		delete sql;
		sql = NULL;
	}
	if (socks != NULL) {
		delete socks;
		socks = NULL;
	}
	if (config.log_fp != NULL) {
		fclose(config.log_fp);
		config.log_fp = NULL;
	}
}

int main(int argc, const char * argv[]) {
	printf("***     driftvmd by Drift Solutions      ***\n");
	printf("*** Copyright 2023. All Rights Reserved. ***\n");

	if (!dsl_init()) {
		printf("Error initializing DSL!\n");
		exit(1);
	}
	atexit(dsl_cleanup);

	if (!LoadConfig()) {
		exit(1);
	}

#if !defined(_WIN32)
	if (config.fFork) {
		pid_t pid = fork();
		if (pid > 0) {
			_exit(0);
		} else if (pid == -1) {
			printf("Error going into the background: %s\n", strerror(errno));
			exit(1);
		}
	}
#endif

#if defined(_WIN32)
	SetConsoleTitle("driftvmd");	
	SetProcessShutdownParameters(0x100, SHUTDOWN_NORETRY);
	SetConsoleCtrlHandler(HandlerRoutine, TRUE);
#else
	struct sigaction sa_old;
	struct sigaction sa_new;

	// set up signal handling
	sa_new.sa_handler = catch_sigint;
	sigemptyset(&sa_new.sa_mask);
	sa_new.sa_flags = 0;
	sigaction(SIGINT, &sa_new, &sa_old);
#endif

	if (access(config.tmp_dir, F_OK) != 0) {
		dsl_mkdir(config.tmp_dir, 0700);
	}
	sstrcat(config.tmp_dir, PATH_SEPS);

	config.log_fp = fopen("debug.log", "wb");
	if (config.log_fp != NULL) {
		/* disable buffering */
		setbuf(config.log_fp, NULL);
	}

	atexit(our_cleanup);
	sql = new DB_MySQL;
	if (!sql->Connect(config.db.host, config.db.user, config.db.pass, config.db.dbname, config.db.port, "utf8")) {
		printf("Error connecting to database: %s\n", sql->GetErrorString().c_str());
		exit(1);
	}

	LoadNetworksFromDB();
	LoadMachinesFromDB();

	socks = new DSL_Sockets();

	set<string> ifaces;
	if (GetNetworkInterfaces(ifaces)) {
		AutoMutex(wdMutex);
		printf("Active interfaces:");
		for (auto& i : ifaces) {
			printf(" %s", i.c_str());
		}
		printf("\n");
		for (auto& x : networks) {
			auto n = x.second;
			if (ifaces.find(n->device) == ifaces.end()) {
				printf("Network %s is not up, activating...\n", n->device.c_str());
				ActivateNetwork(n);
			}
		}
	} else {
		printf("Error getting list of interfaces: %s\n", strerror(errno));
	}

	if (!RPC_Init()) {
		printf("Error initializing the RPC subsystem!\n");
		exit(1);
	}

	time_t lastUpdate = 0;
	while (!config.fShutdown) {
		if (time(NULL) - lastUpdate >= 30) {
			UpdateMachineStatuses();
			lastUpdate = time(NULL);
		}
		RunJobs();
		safe_sleep(1);
	}

	printf("Shutting down...\n");

	RPC_Quit();

	while (DSL_NumThreads()) {
		safe_sleep(100, true);
	}

	printf("Goodbye\n");
	exit(0);
}

void driftvmd_printf(const char * fmt, ...) {
	AutoMutex(wdMutex);
	va_list args;
	va_start(args, fmt);
	char * str = dsl_vmprintf(fmt, args);
	va_end(args);

	if (config.log_fp != NULL) {
		static char buf[64];
		struct tm tm;
		time_t tme = time(NULL);
		strftime(buf, sizeof(buf), "%F %T", localtime_r(&tme, &tm));
		fprintf(config.log_fp, "[%s] %s", buf, str);
	}

#if !defined(_WIN32)
	if (!config.fFork || config.log_fp == NULL) {
#endif
		fwrite(str, strlen(str), 1, stdout);
#if !defined(_WIN32)
	}
#endif
	dsl_free(str);
}
