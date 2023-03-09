//@AUTOHEADER@BEGIN@
/***********************************************************************\
|            Copyright 2022-2023 Drift Solutions / Indy Sams            |
| Docs and more information available at https://www.driftsolutions.dev |
|           This file released under the GNU GPL v3 license,            |
|                see included LICENSE file for details.                 |
\***********************************************************************/
//@AUTOHEADER@END@

#include "driftvmd.h"
#include <list>

class MachineJob {
public:
	MachineStatus func;
	string name;


};
/* Need to be able to iterate, so list instead of queue */
list<MachineJob> jobs;

void QueueMachineJob(MachineStatus func, const string& name) {
	MachineJob j;
	j.func = func;
	j.name = name;

	AutoMutex(wdMutex);
	jobs.push_back(j);
}

string current_job_machine;
bool IsJobQueued(const string& name) {
	AutoMutex(wdMutex);
	if (name == current_job_machine) {
		return true;
	}
	for (auto x = jobs.begin(); x != jobs.end(); x++) {
		if (x->name == name) {
			return true;
		}
	}
	return false;
}

void RunJob(const MachineJob& j) {
	if (j.func == MachineStatus::MS_CREATING) {
		shared_ptr<Machine> c;
		if (GetMachine(j.name, c, false)) {
			CreateMachine(c);
		} else {
			printf("I could not find machine %s!\n", j.name.c_str());
		}
	} else if (j.func == MachineStatus::MS_RUNNING) {
		shared_ptr<Machine> c;
		if (GetMachine(j.name, c, false)) {
			unique_ptr<MachineDriver> d;
			if (GetMachineDriver(c, d)) {
				MachineStatus orig = c->status;
				if (d->Start()) {
					if (orig != c->status) {
						setError("");
						UpdateMachineStatus(c);
					}
				}
			} else {
				printf("Error getting driver for machine %s!\n", j.name.c_str());
			}
		} else {
			printf("I could not find machine %s!\n", j.name.c_str());
		}
	} else if (j.func == MachineStatus::MS_STOPPED) {
		shared_ptr<Machine> c;
		if (GetMachine(j.name, c, false)) {
			unique_ptr<MachineDriver> d;
			if (GetMachineDriver(c, d)) {
				MachineStatus orig = c->status;
				if (d->Stop()) {
					if (orig != c->status) {
						setError("");
						UpdateMachineStatus(c);
					}
				}
			} else {
				printf("Error getting driver for machine %s!\n", j.name.c_str());
			}
		} else {
			printf("I could not find machine %s!\n", j.name.c_str());
		}
	} else if (j.func == MachineStatus::MS_DELETING) {
		shared_ptr<Machine> c;
		if (GetMachine(j.name, c, false)) {
			unique_ptr<MachineDriver> d;
			if (GetMachineDriver(c, d)) {
				if (d->Delete()) {
					setError("");
					RemoveMachineFromDB(c->name);
					RemoveMachine(c->name);
				} else {
					printf("Error deleting machine %s: %s\n", j.name.c_str(), getError().c_str());
				}
			} else {
				printf("Error getting driver for machine %s!\n", j.name.c_str());
			}
		} else {
			printf("I could not find machine %s!\n", j.name.c_str());
		}
	} else {
		printf("I don't know how to run function %u for %s!\n", j.func, j.name.c_str());
	}
}

void RunJobs() {
	while (1) {
		wdMutex.Lock();
		current_job_machine.clear();
		if (jobs.size()) {
			auto j = jobs.front();
			current_job_machine = j.name;
			jobs.erase(jobs.begin());
			wdMutex.Release();
			RunJob(j);
		} else {
			wdMutex.Release();
			break;
		}
	}
}
