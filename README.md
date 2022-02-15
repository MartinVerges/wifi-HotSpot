# wifi-HotSpot
Scripts to manage a raspberry pi as an wlan access point with LTE and Wlan Offloading.

# Why?
It bugged me that devices like the Netgear Nighthawk M1 were either very expensive, or very modest in function.
That's why I decided to sell my M1 again and build my own WLAN hotspot with WLAN offloading and LTE modem based on a Raspberry PI 4.

# What do I need to build my own?
To build your own good WLAN access point with outdoor antenna at the motorhome, the following components are needed:
- Raspberry PI (I use the [4B with 8GB variant](https://www.amazon.de/gp/product/B0899VXM8F))
- SD card (e.g. [SanDisk Extreme Pro 64GB](https://www.amazon.de/gp/product/B07G3GMRYF))
- LTE modem (e.g. [E3372h-320 LTE / 4G](https://www.amazon.de/gp/product/B013UURTL4))
- LTE modem adapter cable (e.g. [SMA female to CRC9 male angled](https://www.amazon.de/gp/product/B091YGRLB4)
- WLAN Stick (e.g. [WLAN Stick 1300 MBit/s Dual Band - WiFi 2,4 + 5Ghz AC](https://www.amazon.de/gp/product/B08N89K14L))
- External antenna (e.g. [Teltonika LTE/GPS/Wi-Fi](https://www.amazon.de/gp/product/B07HSYP97L))

# Which OS do I need to install?
I just use the original 32bit (in the future probably the 64bit) [Raspberry Pi OS Lite](https://www.raspberrypi.org/software/operating-systems/) from the official website. It is based on Debian with which I have long experience and am very satisfied.

# Steps to install the AP

## Install Raspberry OS
Please see [raspberrypi.org/software/](https://www.raspberrypi.org/software/) or other tutorials on how to install the OS image for your PI.

## Install generic stuff
I installed a lot of other packages to have some fun!

```bash
apt -y install iw wireless-tools nmap tcpdump openssh-server bash-completion 
```

## Remove some unused default installed software
As always, there are packages that you don't want or need. This is what I removed from my Image.

```bash
apt purge cron logrotate triggerhappy dphys-swapfile fake-hwclock samba-common resolvconf openresolv dhcpcd5

```

## Install the NetworkManager
My little software effectively just controls the NetworkManager using CLI commands (nmcli).

```bash
apt -y install network-manager network-manager-config-connectivity-debian
```

## Install DHCP and DNS for AP
To provide a DHCP Server and DNS proxy to your connected client devices, you want to install `dnsmasq`:

```bash
apt -y install dnsmasq
```

My configuration looks like this:

```bash
$ cat /etc/dnsmasq.conf 

interface=wlan0,eth0
no-dhcp-interface=eth1,wlan1
dhcp-range=interface:wlan0,192.168.255.101,192.168.255.199,255.255.255.0,14d
dhcp-range=interface:eth0,172.29.0.101,172.29.0.199,255.255.255.0,14d
proxy-dnssec
cache-size=1000
domain-needed
bogus-priv

server=8.8.8.8
server=1.1.1.1
server=208.67.222.222
```

In short, I offer DHCP and DNS on `wlan0` (integrated) and `eth0` (network port).
DNS is proxied to `8.8.8.8` (Google) or `1.1.1.1` (Cloudflare) or `208.67.222.222` (OpenDNS).

## Install the LTE Modem

The LTE Modem can make some problems. These modems can operate in different ways and each has his pros and cons. In my case, I decided to use the USB LTE Modem in a router mode, where the connected USB device simulates an ethernet device `eth1` that runs his own DHCP on range `192.168.8.0/24` (default) and provides a default gateway to the connected raspberry pi. This way you can use the modem integrated webserver to setup all the LTE specific stuff, like PIN code of your SIM or send and receive SMS. All you need to have is the `usb-modeswitch` package that makes sure your modem runs in the right USB mode. Even if you prefer some other way of configuring your LTE modem, I guess you still need that package.

```bash
apt -y install usb-modeswitch
```

## Install my HotSpot Management Software

Place the content of the git `www` folder into your raspberry `/var/www`.
Then install PHP to run the software itself.

```bash
apt -y install php-cli
```

Create a SystemD Service to start it up when the Raspberry boots up.

```bash
$ cat /etc/systemd/system/php-wifi-ap.service
[Unit]
Description=Wifi AP Management

[Service]
Type=simple
WorkingDirectory=/var/www/
ExecStart=php -S 0.0.0.0:80 -t /var/www/html/ router.php
Restart=always
RestartSec=30

[Install]
WantedBy=multi-user.target
```

Now you can install and start the service using `systemctl daemon-reload` and `systemctl enable --now php-wifi-ap.service`.

## Install the HotSpot itself and broadcast your SSID

```bash
$ cat /etc/hostapd/hostapd.conf
# This configuration is just insane and totally user unfriendly. Good luck!

interface=wlan0
driver=nl80211
ctrl_interface=/var/run/hostapd
ctrl_interface_group=0
max_num_sta=10

# WIFI security
auth_algs=1                  # WPA
wpa=2                        # WPA2 only
wpa_key_mgmt=WPA-PSK
rsn_pairwise=CCMP
wpa_passphrase=XXXXXXXXXXXXX
broadcast_deauth=1           # Disconnect clients on stop

# WIFI configuration
ssid=XXXXXXXXXXXXXXXXXX
country_code=DE
hw_mode=a
channel=36
wmm_enabled=1 # QoS support
ht_capab=[MAX-AMSDU-3839][HT40+][SHORT-GI-20][SHORT-GI-40][DSSS_CCK-40][HTC-VHT]
# [HT40+] = both 20 MHz and 40 MHz with secondary channel above the primary channel
# [MAX-AMSDU-7935] = Maximum A-MSDU length for 7935 octets (3839 octets if not set)
# [SHORT-GI-20] = 20 Mhz short GI can improve the throughput about 10%
# [SHORT-GI-40] = 40 Mhz short GI can improve the throughput about 10%
# [DSSS_CCK-40] = DSSS/CCK Mode in 40 MHz 
# [HTC-VHT] = STA support for receiving a VHT variant HT Control
ieee80211d=1        # 802.11d  support
ieee80211h=1        # 802.11h  (DFS=Dynamic Frequency Selection, to prevent interferences with radars)
ieee80211n=1        # 802.11n  (HT) support, requires WMM
ieee80211ac=1       # 802.11ac (VHT) support, requires WMM
# see https://en.wikipedia.org/wiki/List_of_WLAN_channels#5_GHz_(802.11a/h/j/n/ac/ax)
# 0 = 20 or 40 MHz operating Channel width
# 1 = 80 MHz channel width
# 2 = 160 MHz channel width
# 3 = 80+80 MHz channel width
vht_oper_chwidth=1
#vht_oper_centr_freq_seg0_idx=116
#vht_oper_centr_freq_seg1_idx=116
vht_capab=[MAX-AMSDU-3839][HT40+][SHORT-GI-40]
```

## Make it a router

You have to make sure to forward packages that we receive from external

```bash
$ cat /etc/sysctl.d/50-router.conf
# IPv4
net.ipv4.ip_forward=1
net.ipv4.ip_forward_use_pmtu=1

# IPv6, I don't care at the moment
net.ipv6.conf.all.disable_ipv6 = 1
```
Then run `sysctl -p` to enable it.

