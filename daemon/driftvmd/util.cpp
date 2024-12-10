//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"

thread_local string errstr;
void setError(const char * fmt, ...) {
	char buf[256];
	va_list args;
	va_start(args, fmt);
	vsnprintf(buf, sizeof(buf), fmt, args);
	va_end(args);
	errstr = buf;
}
string getError() {
	return errstr;
}

string GetTempDirFile(string fn) {
	stringstream sstr;
	sstr << config.tmp_dir << fn;
	return sstr.str();
}

int GetNumCPUs() {
#ifdef _WIN32
	SYSTEM_INFO si;
	GetSystemInfo(&si);
	return si.dwNumberOfProcessors;
#else
	return sysconf(_SC_NPROCESSORS_CONF);
#endif
}

/*
string escapeshellarg(const string& str) {
    size_t len = str.length();
    const char * p = str.c_str();
    string ret = "'";
    for (size_t i=0; i < len; i++, p++) {
        if (*p == '\'' || *p == '\\') {
            ret += '\\';
        }
        ret += *p;
    }
	ret += "'";
    return ret;
}
*/
