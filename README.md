# DriftVM
Virtual Machine Manager for LXC/KVM

## Installation
These directions were made on Debian 11 but should work for Ubuntu as well. Other distros you will have to figure out on your own. You are expected to know the basics of running a Linux server before using DriftVM, such as configuring a database and user in MariaDB and setting up your vhost in Apache.

#### Dependencies

There are certain dependencies you will need for DriftVM:

apt install apache2 libapache2-mod-php php-cli php-json php-mysqlnd php-curl bridge-utils iptables psmisc mariadb-server lm-sensors git cmake build-essential libevent-dev libmariadb-dev libmariadb-dev-compat pkg-config

If you want to use LXC containers:

apt install lxc lxc-templates

If you want to use KVM:

apt install qemu-system

#### Set up web admin panel

- Clone the DriftVM repo to your location of choice: git clone https://github.com/DriftSolutions/DriftVM
- Create your vhost of choice in Apache pointing to the www folder. Make sure PHP is enabled in your Apache config and obviously SSL is preferred.
