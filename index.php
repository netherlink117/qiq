<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
$auth = true;
$valid_credentials = [
  [
    'user' => 'user',
    'password' => 'password'
  ],
  [
    'user' => 'usuario',
    'password' => 'contraseña'
  ]
];
$hidden_files = [
  'zip',
  'txt',
  'php'
];
$visible_files = [
  'jpeg',
  'jpg',
  'png',
  'bmp',
  'gif',
  'ts',
  'mkv',
  'webm',
  'mp4',
  'flv'
];
// check request
if (isset($_SERVER['HTTP_ACCEPT'])) {
  header('X-Content-Type-Options: nosniff');
  // check header to respond with json or html
  if (strpos($_SERVER['HTTP_ACCEPT'], 'json') !== false || strpos($_SERVER['HTTP_ACCEPT'], 'JSON') !== false) {
    //header('Content-Type: application/json; charset=utf-8');
    //header('Cache-Control: no-cache');
    // check token
    if (!isset($_SERVER['HTTP_TOKEN'])) {
      // check credentials
      if (isset($_POST['user']) && isset($_POST['password'])) {
        // iterate credentials
        foreach ($valid_credentials as &$valid_credential) {
          // check credential
          if ($_POST['user'] === $valid_credential['user'] && $_POST['password'] === $valid_credential['password']) {
            // get session length
            $session_length = isset($_POST['session_length']) ? $_POST['session_length'] : 3600;
            // generate expiration timestamp
            $expiration_timestamp = time() + ($session_length * 1000);
            // cipher method
            $ciphering = "AES-128-CTR";
            // encryption method
            $iv_length = openssl_cipher_iv_length($ciphering);
            // encryption options for zero padding
            $options = 0;
            // initialization vector for encryption
            $encryption_iv = '1234567891011121';
            // encryption key (server key)
            $encryption_key = "secret";
            // encrypt the timestamp
            $token = openssl_encrypt($expiration_timestamp, $ciphering, $encryption_key, $options, $encryption_iv);
            // return token
            die(json_encode([
              'token' => $token
            ]));
          }
        }
      }
      // throw unauthorized message with headers
      header('HTTP/1.0 401 Unauthorized');
      die(json_encode([
        'message' => 'Unauthorized'
      ]));
    }
    // cipher method
    $ciphering = "AES-128-CTR";
    // encryption method
    $iv_length = openssl_cipher_iv_length($ciphering);
    // encryption options for zero padding
    $options = 0;
    // initialization vector for decryption
    $decryption_iv = '1234567891011121';
    // decryption key (server key)
    $decryption_key = "secret";
    // decrypt the token
    $timestamp = openssl_decrypt($_SERVER['HTTP_TOKEN'], $ciphering, $decryption_key, $options, $decryption_iv);
    // validate expiration timestamp
    if ($timestamp < time()) {
      // throw unauthorized message with headers
      header('HTTP/1.0 401 Unauthorized');
      die(json_encode([
        'message' => 'Unauthorized'
      ]));
    }
    $response = [];
    // get folder path where current php script (this) is stored
    $dir = dirname(__FILE__);
    // scan folder for files and folders
    $paths = scandir($dir);
    sort($paths);
    // check for breadcrumbs parameter
    if (isset($_GET['breadcrumbs'])) {
      // separate the folders according to the site's root path, but for the current path of this php
      $bread_crumbs = explode('/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $dir));
      // add to response
      $response['breadcrumbs'] = $bread_crumbs;
    }
    // check request for folders parameter, no matter type, if present, response must contain folders array
    if (isset($_GET['folders'])) {
      // class for custom folder attributes
      class Folder implements \ArrayAccess
      {
        public function offsetExists($offset)
        {
          return isset($this[$offset]);
        }
        public function offsetGet($offset)
        {
          return isset($this[$offset]) ? $this[$offset] : null;
        }
        public function offsetSet($offset, $value)
        {
          $this[$offset] = $value;
        }
        public function offsetUnset($offset)
        {
          unset($this[$offset]);
        }
      }
      // folders
      $folders = [];
      // loop paths to search folders
      foreach ($paths as $path) {
        // check each "path" to exclude the ones that points out of the current path and accept only folders inside current path
        if (is_dir($path) && $path != '.' && $path != '..') {
          // copy this file inside each folder
          copy($dir . '/index.php', $dir . '/' . $path . '/index.php');
          // create an instance of custom class "Folder"
          $folder = new Folder();
          // add attribute name
          $folder->name = $path;
          // push instanced to folders array
          array_push($folders, $folder);
        }
      }
      $response['folders'] = $folders;
    }
    if (isset($_GET['files'])) {
      class File implements \ArrayAccess
      {
        public function offsetExists($offset)
        {
          return isset($this[$offset]);
        }
        public function offsetGet($offset)
        {
          return isset($this[$offset]) ? $this[$offset] : null;
        }
        public function offsetSet($offset, $value)
        {
          $this[$offset] = $value;
        }
        public function offsetUnset($offset)
        {
          unset($this[$offset]);
        }
        public static function human_filesize($bytes, $decimals = 2)
        {
          $sz = 'BKMGTP';
          $factor = floor((strlen($bytes) - 1) / 3);
          return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
        }
      }
      //files logic here
      $files = [];
      $add = 0;
      $accept = 0;
      if (isset($_GET['quantity'])) {
        $accept = $_GET['quantity'];
      } else {
        $accept = PHP_INT_MAX;
      }
      foreach ($paths as $path) {
        if (is_file($dir . '/' . $path)) {
          $file = new File();
          $file->extension = explode('.', $path)[count(explode('.', $path)) - 1];
          $file->path = $path;
          $stats = stat($dir . '/' . $path);
          $file->size = File::human_filesize($stats['size']);
          $file->last_access_timestamp = $stats['atime'];
          $file->last_modification_timestamp = $stats['mtime'];
          $file->creation_timestamp = $stats['ctime'];
          if (isset($_GET['last']) && isset($_GET['quantity'])) {
            if ($_GET['last'] === $path) {
              $add = true;
            }
          } else {
            $add = true;
          }
          if (!in_array($file->extension, $hidden_files) && in_array($file->extension, $visible_files) && $accept > 0 && $add) {
            array_push($files, $file);
            $accept--;
          }
        }
      }
      $response['files'] = $files;
    }
    die(json_encode($response));
  } else {
    //header('Content-Type: text/html; charset=utf-8');
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Qiq</title>
      <style>
        * {
          box-sizing: border-box;
          padding: 0;
          margin: 0;
          color: #00ffff;
          background-color: #000000;
          font-family: monospace, sans-serif;
          outline: none;
        }
        a:hover {
          color: #ffff00;
        }
        body> :nth-child(4) {
          position: fixed;
          right: 0;
          top: 0;
          height: 100vh;
          width: 100vw;
          z-index: 5;
          background-color: rgba(0, 0, 0, 0.11);
        }
        body> :nth-child(5) {
          position: fixed;
          right: 10px;
          top: 10px;
          border: 1px solid #ff00ff;
          padding: 7px;
          z-index: 7;
        }
        body> :nth-child(5) ul {
          list-style: none;
        }
        body> :nth-child(5) hr {
          height: 1px;
          background: #ff00ff;
          border: none;
          margin: 7px 0px;
        }
        body> :nth-child(5) li {
          padding: 7px;
          cursor: default;
        }
        body> :nth-child(5) li button {
          border: none;
        }
        body> :nth-child(5) li button:hover {
          color: #ffff00;
        }
        header {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          display: flex;
          flex-wrap: wrap;
          align-items: center;
          justify-content: space-between;
          border: 1px solid #ff00ff;
          z-index: 3;
        }
        header {
          padding: 7px;
        }
        header a,
        header nav a {
          text-decoration: none;
          padding: 0px 3px;
        }
        header button {
          border: none;
          padding: 3px 7px;
          margin-right: 7px;
          color: #00ffff;
        }
        header button:hover {
          color: #ffff00;
        }
        main {
          margin-top: 3.5em !important;
        }
        form {
          display: flex;
          flex-wrap: wrap;
          justify-content: center;
          align-items: center;
          align-content: stretch;
          flex-flow: column;
          border: 1px solid #00ffff;
          margin-left: auto;
          margin-right: auto;
          max-width: 12.28%;
          padding: 7px;
          text-align: center;
        }
        form input {
          width: 98%;
          margin: 0px 1%;
          padding: 3px 7px;
        }
        form label {
          padding: 7px;
        }
        form input[type=text],
        form input[type=password] {
          color: #ffff00;
          border: none;
          border-bottom: 1px solid #00ffff;
        }
        form input[type=button] {
          color: #ffff00;
          border: none;
          padding: 7px;
        }
        main>div {
          display: flex;
          flex-wrap: wrap;
          justify-content: center;
          align-items: center;
          align-content: stretch;
        }
        main> :nth-child(1)>a {
          padding: 1em;
          border-radius: 3px;
          cursor: pointer;
          margin: 7px;
        }
        main> :nth-child(2)>div {
          max-width: 12.28%;
          padding: 7px;
          margin: 1%;
          text-align: center;
          word-wrap: break-word;
        }
        main> :nth-child(2)>div img,
        main> :nth-child(2)>div video {
          overflow: hidden;
          white-space: nowrap;
          width: 100%;
          border: 1px solid #ff00ff;
          padding: 3px;
          margin-bottom: 14px;
        }
        main> :nth-child(2)>div button {
          width: 100%;
          padding: 3px;
          margin-bottom: 14px;
          height: 100%;
          cursor: default;
          border: none;
        }
        main> :nth-child(2)>div button:hover {
          color: #ffff00;
        }
        main> :nth-child(2)>div a,
        main> :nth-child(2)>div div {
          text-align: center;
        }
        @media only screen and (max-width: 1919px) {
          main>div:nth-child(2)>div,
          form {
            max-width: 23%;
          }
        }
        @media only screen and (max-width: 1007px) {
          main>div:nth-child(2)>div,
          form {
            max-width: 31.33%;
          }
        }
        @media only screen and (max-width: 640px) {
          main>div:nth-child(2)>div,
          form {
            max-width: 98%;
          }
        }
      </style>
    </head>
    <body>
      <script>
        // this
        const page = window.location.href;
        // data
        let data = null;
        // files per fetch
        const quantity = 20;
        // files counter
        let fc = 0;
        // html basic skeleton
        // header
        let header = document.createElement("header");
        // header default cocntent
        header.innerHTML = '<a href="/">Qiq</a>';
        // main
        let main = document.createElement("main");
        // div for folders at the top
        let foldersDivision = document.createElement("div");
        main.appendChild(foldersDivision);
        // div for files at the bottom
        let filesDivision = document.createElement("div");
        main.appendChild(filesDivision);
        // div for login form
        let login = document.createElement("form");
        main.appendChild(login);
        // div content
        let user_label = document.createElement("label");
        login.appendChild(user_label);
        user_label.innerText = "User";
        let user_input = document.createElement("input");
        login.appendChild(user_input);
        user_input.setAttribute("placeholder", "User");
        user_input.setAttribute("type", "text");
        let password_label = document.createElement("label");
        login.appendChild(password_label);
        password_label.innerText = "Password";
        let password_input = document.createElement("input");
        login.appendChild(password_input);
        password_input.setAttribute("placeholder", "Password");
        password_input.setAttribute("type", "password");
        let submit = document.createElement("input");
        login.appendChild(submit);
        submit.setAttribute("type", "button");
        submit.setAttribute("value", "Login");
        submit.onclick = () => {
          // login fetch
          fetch(page, {
              method: "POST",
              headers: {
                Accept: "application/json",
                "Content-Type": "application/x-www-form-urlencoded",
              },
              body: "user=" + user_input.value + "&password=" + password_input.value,
            })
            .then((response) => {
              if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
              }
              return response.json();
            })
            .then((data) => {
              // remove login form from the DOM
              main.removeChild(login);
              // insert folders/files ocntainers to the DOM
              main.appendChild(foldersDivision);
              main.appendChild(filesDivision);
              // store token
              localStorage.setItem("token", data.token);
              // remove default header and append breadcrumbs nav
              showBreadcrumbs();
              // also get first set of folders/files
              getData();
            })
            .catch((err) => {
              // any error during login POST
              console.log(err);
            });
        };
        // get data
        function getData(params = "?files=true&folders=true&quantity=" + quantity) {
          // fetch to get folders and files
          fetch(page + params, {
              headers: {
                Token: localStorage.getItem("token"),
                "Content-Type": "application/json",
                Accept: "application/json",
              },
            })
            .then((response) => {
              if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
              }
              return response.json();
            })
            .then((json) => {
              // check for content already downloaded
              if (data === null) {
                data = {
                  folders: [],
                  files: [],
                };
              }
              // check for folders on response
              if (json.folders !== undefined && json.folders !== null) {
                // check for repeated folders
                json.folders.forEach((folder, index) => {
                  const found = data.folders.find((f, i) => {
                    return f.name === folder.name;
                  });
                  if (found === undefined) {
                    folder.shown = false;
                    data.folders.push(folder);
                  }
                });
              }
              // check for files on response
              if (json.files !== undefined && json.files !== null) {
                // check for repeated files
                json.files.forEach((file, index) => {
                  const found = data.files.find((f, i) => {
                    return f.path === file.path;
                  });
                  if (found === undefined) {
                    file.shown = false;
                    data.files.push(file);
                  }
                });
              }
              // insert new folders/files to the DOM
              showData();
              // check for any error message
              if (json.message !== undefined && json.message !== null) {
                // remove authentication stuff
                localStorage.removeItem("token");
                // remove files/folders from the DOM
                main.removeChild(foldersDivision);
                main.removeChild(filesDivision);
                // and append the login form
                main.appendChild(login);
              }
            })
            .catch((err) => {
              // any other error
              console.log(err);
            });
        }
        // create breadcrumbs
        function showBreadcrumbs() {
          // remove first and last slash
          const breadcrumbs_array = window.location.pathname
            .replace(/^\/|\/$/g, "")
            .split("/");
          // remove default header content
          header.innerHTML = "";
          // create nav element fot breadcrumbs
          let nav = document.createElement("nav");
          header.appendChild(nav);
          // root path
          let breadcrumb = "/";
          // root breadcrumb
          let root_anchor = document.createElement("a");
          nav.appendChild(root_anchor);
          root_anchor.setAttribute("href", breadcrumb);
          root_anchor.innerText = "/root";
          // leveled breadcrumb for folders
          breadcrumbs_array.forEach((item, index) => {
            if (item !== "") {
              // breadcrumb for current index loop
              let anchor = document.createElement("a");
              nav.appendChild(anchor);
              breadcrumb += item + "/";
              anchor.setAttribute("href", breadcrumb);
              anchor.innerText = "/" + item;
            }
          });
          showOptions();
        }
        // show options menu
        function showOptions() {
          // options button for the header to open options menu
          let optionsButton = document.createElement("button");
          header.appendChild(optionsButton);
          optionsButton.innerText = "Options";
          optionsButton.onclick = () => {
            optionsDiv.style.display =
              optionsDiv.style.display === "none" ? "block" : "none";
            optionsCloseBackgroundInvisible.style.display =
              optionsCloseBackgroundInvisible.style.display === "none" ?
              "block" :
              "none";
          };
          // background to close options menu
          let optionsCloseBackgroundInvisible = document.createElement("div");
          document.body.appendChild(optionsCloseBackgroundInvisible);
          optionsCloseBackgroundInvisible.style.display = "none";
          optionsCloseBackgroundInvisible.onclick = () => {
            optionsDiv.style.display =
              optionsDiv.style.display === "none" ? "block" : "none";
            optionsCloseBackgroundInvisible.style.display =
              optionsCloseBackgroundInvisible.style.display === "none" ?
              "block" :
              "none";
          };
          // options menu container div
          let optionsDiv = document.createElement("div");
          document.body.appendChild(optionsDiv);
          optionsDiv.style.display = "none";
          // list of visibility options for images/videos
          let visibilityOptionsList = document.createElement("ul");
          optionsDiv.appendChild(visibilityOptionsList);
          // option for images visibility
          let imagesVisibilityOption = document.createElement("li");
          visibilityOptionsList.appendChild(imagesVisibilityOption);
          imagesVisibilityOption.innerHTML = "<button>Toggle images</button>";
          // init image visibility with toggleImages
          let toggleImages = JSON.parse(localStorage.getItem("toggleImages") || "true");
          imagesVisibilityOption.onclick = () => {
            // invert default/loaded value
            toggleImages = !toggleImages;
            // store for furter use
            localStorage.setItem("toggleImages", toggleImages);
            // apply effect
            [...document.getElementsByTagName("img")].forEach((element, index) => {
              element.parentElement.style.display = toggleImages ? "block" : "none";
            });
            // hide options menu and background (close menu)
            optionsDiv.style.display = "none";
            optionsCloseBackgroundInvisible.style.display = "none";
          };
          // option for videos visibility
          let videosVisibilityOption = document.createElement("li");
          visibilityOptionsList.appendChild(videosVisibilityOption);
          videosVisibilityOption.innerHTML = "<button>Toggle videos</button>";
          // init video visibility with toggleVideos
          let toggleVideos = JSON.parse(localStorage.getItem("toggleVideos") || "true");
          videosVisibilityOption.onclick = () => {
            // invert default/loaded value
            toggleVideos = !toggleVideos;
            // store for furter use
            localStorage.setItem("toggleVideos", toggleVideos);
            // apply effet
            [...document.getElementsByTagName("video")].forEach((element, index) => {
              element.parentElement.style.display = toggleVideos ? "block" : "none";
            });
            // hide options menu and background (close menu)
            optionsDiv.style.display = "none";
            optionsCloseBackgroundInvisible.style.display = "none";
          };
        }
        // show data
        function showData() {
          // insert folder to the DOM
          data.folders.forEach((folder, index) => {
            // check for folder already inserted
            if (!folder.shown) {
              // anchor tag for the new brand folder
              let anchor = document.createElement("a");
              foldersDivision.appendChild(anchor);
              anchor.setAttribute("href", "./" + folder.name);
              // anchor.setAttribute('target', '_blank');
              anchor.innerText = folder.name;
              // set this new folder as already shown for future insertions
              data.folders[index].shown = true;
            }
          });
          // get the visibility options from storage
          const toggleImages = JSON.parse(
            localStorage.getItem("toggleImages") || "true"
          );
          const toggleVideos = JSON.parse(
            localStorage.getItem("toggleVideos") || "true"
          );
          // insert files to the DOM
          data.files.forEach((file, index) => {
            // check for files already inserted
            if (!file.shown) {
              // file container div
              let fileDivision = document.createElement("div");
              filesDivision.appendChild(fileDivision);
              // check file type
              if (
                file.extension === "mkv" ||
                file.extension === "webm" ||
                file.extension === "mp4" ||
                file.extension === "flv"
              ) {
                // video content media
                let media = document.createElement("video");
                fileDivision.appendChild(media);
                media.setAttribute("preload", "metadata");
                media.setAttribute("controls", "true");
                let source = document.createElement("source");
                media.appendChild(source);
                source.setAttribute("src", "./" + file.path);
                fileDivision.style.display = toggleVideos ? "block" : "none";
              } else if (
                file.extension === "png" ||
                file.extension === "gif" ||
                file.extension === "jpg" ||
                file.extension === "jpeg" ||
                file.extension === "bmp"
              ) {
                // image content media
                let media = document.createElement("img");
                fileDivision.appendChild(media);
                media.setAttribute("src", "./" + file.path);
                media.setAttribute("alt", file.path);
                // set here the visibility for container div as not all the divisions have same file type
                fileDivision.style.display = toggleImages ? "block" : "none";
              }
              // now file (video/image) content info
              // anchor to download the file, also show the file name
              let anchorPath = document.createElement("a");
              fileDivision.appendChild(anchorPath);
              anchorPath.setAttribute("href", "./" + file.path);
              anchorPath.setAttribute("target", "_blank");
              anchorPath.innerText = file.path;
              // div to show the file extension, commented as is quite useless when full file path/name is shown
              // let extension = document.createElement("div");
              // fileDivision.appendChild(extension);
              // extension.innerText = file.extension;
              // file size div
              let size = document.createElement("div");
              fileDivision.appendChild(size);
              size.innerText = file.size;
              // set this new file as already shown for future inserttions
              data.files[index].shown = true;
            }
          });
          // check for changes on files array from data
          if (data.files.length > fc) {
            // append a "Load more..." button to the DOM
            let fileDivision = document.createElement("div");
            filesDivision.appendChild(fileDivision);
            let load_button = document.createElement("button");
            fileDivision.appendChild(load_button);
            load_button.innerText = "Load more...";
            load_button.onclick = () => {
              // remove this button's container and it's content for the while
              filesDivision.removeChild(fileDivision);
              // use the fetch to get folders and files
              getData(
                "?files=true&folders=true&last=" +
                data.files[data.files.length - 1].path +
                "&quantity=" +
                quantity
              );
            };
          }
          // update the files counter for future comparisons
          fc = data.files.length;
        }
        // authentication and content
        if (
          localStorage.getItem("token") !== null &&
          localStorage.getItem("token") !== undefined
        ) {
          // remove login form if it's there
          main.removeChild(login);
          // append folders/files containers
          main.appendChild(foldersDivision);
          main.appendChild(filesDivision);
          // also show breadrumbs, obviously
          showBreadcrumbs();
          // and use the fetch to get folders/files
          getData();
        } else {
          // remove folders/files
          main.removeChild(foldersDivision);
          main.removeChild(filesDivision);
          // and append the login form
          main.appendChild(login);
        }
        // insert all the content at the end of the script using prepend
        document.body.prepend(main);
        document.body.prepend(header);
      </script>
    </body>
    </html>
<?php }
} else {
  header('HTTP/1.0 400 Bad Request');
  die("Bad Request.");
}
?>
