# speedtest-x
![GitHub Actions Build Status](https://img.shields.io/github/workflow/status/badapple9/speedtest-x/Build%20Docker%20Image) ![GitHub last commit](https://img.shields.io/github/last-commit/badapple9/speedtest-x) ![GitHub](https://img.shields.io/github/license/badapple9/speedtest-x)

This project is an extension of [LibreSpeed](https://github.com/librespeed/speedtest), LibreSpeed is a pretty lightweight speedtest tool.

speedtest-x uses file datebase to save speedtest results from various users. Thus you can check out different results from various countries/regions.

[中文文档](https://github.com/BadApple9/speedtest-x/blob/master/README_CN.md)

[Join Telegram group](https://t.me/xiaozhu5)

**❗ Warning**：Based on the principle of web speedtest, this program will generate garbage files for tester to download them to calculate the downstream network bandwidth from server to local. There may be abuses by malicious tester in a certain extent, after shared your speedtest website in public, please pay attention to the condition of your server traffic to avoid an traffic overload.

## Features and extensions
 - Self-hosted lightweight speedtest page
 - User speedtest result datasheet
 - No MySQL, but lightweight file database
 - Use [ip.sb](https://ip.sb) to get IP info by default

## Quick start

### Deploy by Docker (Supported platforms: AMD64/ARM64)
1. Pull [Image](https://hub.docker.com/r/badapple9/speedtest-x) `docker pull badapple9/speedtest-x`
2. Run container `docker run -d -p 9001:80 -it badapple9/speedtest-x`

Optional arguments:

> **-e MAX_LOG_COUNT=100**: Maximum results size, 100 by default
>
> **-e IP_SERVICE=ip.sb**: IP info provider (Options: ip.sb / ipinfo.io), ip.sb by default
>
> **-e SAME_IP_MULTI_LOGS=false**: Whether to allow the same user IP to record multiple speedtest results, false by default.

> If you want support IPV6 in a Docker container, edit `/etc/docker/daemon.json`:
> ```
> {
>   "ipv6": true,
>   "fixed-cidr-v6": "fd00::/80",
>   "experimental": true,
>   "ip6tables": true
> }
> ```

3. Open `{your_ip}:9001`

### General deploy (Require: PHP5.6+)

1. Download repository files and unzip to website directory, open `{your_domain_name}/index.html`.
2. Open `{your_domain_name}/results.html` to check out speedtest result datasheet.

## Settings

`backend/config.php`:
> 
> `MAX_LOG_COUNT = 100`：Maximum results size, 100 by default
>
> `IP_SERVICE = 'ip.sb'`：IP info provider (Options: ip.sb / ipinfo.io), ip.sb by default
>
> `SAME_IP_MULTI_LOGS = false`：Whether to allow the same user IP to record multiple speedtest results, false by default.


## Screenshots

![index](https://raw.githubusercontent.com/BadApple9/images/main/indexdemo.png)
![results](https://raw.githubusercontent.com/BadApple9/images/main/resultsdemo.png)

## See also
 - [LibreSpeed](https://github.com/librespeed/speedtest)
 - [ip.sb](https://ip.sb)
 - [SleekDB](https://github.com/rakibtg/SleekDB)

## Contributors

<a href="https://github.com/badapple9/speedtest-x/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=badapple9/speedtest-x" />
</a>

## License

See [License](https://github.com/BadApple9/speedtest-x/blob/master/LICENSE)
