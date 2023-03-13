# DriftVM
Lightweight Virtual Machine Manager for LXC/KVM<br />
I made this versus using Proxmox because I like to run my LXC containers in one big shared FS instead in LVM/disk images/etc. so I don't have to worry about growing/shrinking them. Proxmox has no way to do this (as far as I could tell) so I put this together for my own use but figured I would share. Although it does support using disk images for LXC if you like and of course for KVM.

## Installation
These directions were made on Debian 11 but should work for Ubuntu as well. Other distros you will have to figure out on your own. You are expected to know the basics of running a Linux server before using DriftVM, such as configuring a database and user in MariaDB and setting up your vhost in Apache.

### Dependencies

There are certain dependencies you will need for DriftVM:

apt install apache2 libapache2-mod-php php-cli php-json php-mysqlnd php-curl bridge-utils iptables psmisc mariadb-server lm-sensors git cmake build-essential libevent-dev libmariadb-dev libmariadb-dev-compat pkg-config

If you want to use LXC containers:

apt install lxc lxc-templates

If you want to use KVM:

apt install qemu-system libvirt-daemon-system virtinst libosinfo-bin osinfo-db

### Installation

#### Code and database

- Clone the DriftVM repo to your location of choice: git clone https://github.com/DriftSolutions/DriftVM
- Create a database in MariaDB/MySQL and add a user with access to it.
- Import driftvm.sql into it to create the tables.

#### Set up web admin panel

- Create your vhost of choice in Apache pointing to the www folder. Make sure PHP is enabled in your Apache config and obviously SSL is preferred.
- In the www folder rename config.inc.php.example to config.inc.php and fill in the configuration fields.

#### Set up the driftvmd daemon

- In the daemon folder, make a new directtory called 'build' and cd to it.
- Run: cmake ..
- Run: make -j number_of_cpu_cores
- Copy or move ../driftvmd.conf to your build folder.
- Open driftvmd.conf and fill in the configuration fields. Make sure the database details and RPC user/pass/port match what you have in your web panel config.inc.php.
- Run: ./driftvmd
- You'll want to make driftvmd run at startup and/or have a cron job run it.

#### Log in to the web admin panel

- Point your browser to your DriftVM vhost, on your first login it will create your user for you automatically.
- You are good to go :)

### Credits

This project uses our Drift Standard Libraries (DSL), mainly 3-clause BSD, license info at https://www.driftsolutions.dev/wiki/Drift_Standard_Libraries

We do use a few 3rd party libraries/code:

libevent, 3-clause BSD, https://libevent.org/LICENSE.txt <br />
UniValue, MIT license, daemon/univalue/COPYING<br />
Base64 Encoding/Decoding, Public Domain
