# Qiq
Qiq is a small PHP script that serves as a gallery every image and video of the folder (and subfolders) where the script was placed, using only the built in PHP's server.
It was made with the objective of serving a bunch of pictures from a folder, but later, the support for some videos was added. Any ways, the script was made just for fun and as a tool for share pictures and videos in local network where there is no much danger if you have well configured firewall and network devices. Also, notice that there could be some improvements for security and performance, use it at you own risk.
## Installation and usage
Just place the index.php file in any folder you want to share as a web gallery. If you don't want to use authentication, set to false the `$auth` variable at the declaration on the top of the script's content, otherwise change the user and password from the array at the `$valid_credentials` declaration, then open a CLI (terminal, command prompt, etc) in that same folder where the script is and start the built in PHP server with the command:
```bash
php -S 0.0.0.0:8080
```
Afther executing that command your pictures and videos from that folder will be shared in a basic gallery with view only permissions (there is no write or delete support), just open a browser from a device in your local network and type the "server" machine's IP and port as you typed in the command (the example above users the 8080 port, i.e. http<nolink>://192.168.0.1:8080/).
NOTE: The script copies itself to the subfolders to server them as sub galleries, so be awared that multiple "index.php" might appear on the child directories. To avoid that behavior, just remove the copy stuff at line `139`, but it may cause Qiq to not show all your stuff nor work propperly.

## Screenshots
### Desktop
![Image 1 Desktop view](https://github.com/netherlink117/qiq/blob/277d20c0c6e9a0a9cc9d7f486199874592d14bd5/screenshot_desktop.png)
### Mobile
![Image 2 Mobile view](https://github.com/netherlink117/qiq/blob/277d20c0c6e9a0a9cc9d7f486199874592d14bd5/screenshot_movile.png)
## Known issues
* Directories with a `.` in its name confuses the PHP's built in server, taking them as files. An alternative is to use an HTTP server with PHP well configured.
* Directories with `#` in its name confuses almost any server (if not every server) due HTML's id notation at URL links.
* PHP's build in server is single threated, so it may be slow and respond one request at the time.
## License
The script's code shared on this repository is shared under [The Unlicense](https://unlicense.org) license.
