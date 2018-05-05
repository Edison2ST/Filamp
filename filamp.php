<?php
// User variables
$parent = 0; // Number of parent directories (0 if current directory)
$host = "localhost";
$user = "root";
$password = "";
$database = "filamp";
$GLOBALS["admin"] = true; // When there are no users in the db, set this variable to true and register in ?new (Example: http://localhost/filamp.php?new). NOTE: Remember to delete this variable when you have registered your account, or change it to false! Otherwise, server security is compromised
// $prefix = "your_prefix_"; // Default: "filamp_"
// End of user variables
// MySQL class
class Database
{
    private $mysqli;
    private $prefix;
    private $user;
    private $password;
    public $privilege;
    private $token;
    private $expires;
    private $admin;
    public function __construct(string $host, string $user, string $password, string $database, string $prefix = "filamp_")
    {
        $this->prefix = $prefix;
        $this->admin = $GLOBALS["admin"] ?? false;
        $this->mysqli = new mysqli($host, $user, $password) or die ("Error: Unable to connect to MySQL.<br />Debugging errno: ".$this->mysqli->connect_errno."<br />Debugging error: ".$this->mysqli->connect_error);
        $this->mysqli->select_db($database) or exit("Error: Unknown database");
        $this->mysqli->query("CREATE TABLE IF NOT EXISTS ".$prefix."users(user VARCHAR(20), password CHAR(53), privilege INT DEFAULT 1, token CHAR(156) DEFAULT '', expires TIMESTAMP DEFAULT '0000-00-00 00:00:00')") or exit("Error in MySQL");
        $this->mysqli->query("CREATE TABLE IF NOT EXISTS ".$prefix."log(user VARCHAR(20), id INT, dir1 VARCHAR(250), dir2 VARCHAR(250), dir3 VARCHAR(250), time TIMESTAMP)") or exit("Error in MySQL");
    }
    public function setUser(string $user) : bool
    {
        if (strlen($user) < 21 && ctype_alnum($user))
        {
            $stmt = $this->mysqli->prepare("SELECT password,privilege,token,expires FROM ".$this->prefix."users WHERE user=?") or exit("Error in MySQL");
            $stmt->bind_param("s", $user) or exit("Error in MySQL");
            $stmt->execute() or exit("Error in MySQL");
            $query = $stmt->get_result() or exit("Error in MySQL");
            if ($query->num_rows === 1)
            {
                $this->user = $user;
                $element = $query->fetch_row();
                $this->password = "$2y$11$".$element[0];
                $this->privilege = $element[1];
                $this->token = $element[2];
                $this->expires = $element[3];
                $b = true;
            }
            $query->free();
            $stmt->close();
            return isset($b);
        }
        return false;
    }
    public function checkLogin(string $password)
    {
        sleep(2);
        if ($this->password === crypt($password, $this->password))
        {
            $this->token = bin2hex(random_bytes(78));
            $this->expires = date("Y-m-d H:i:s", time()+86400);
            $stmt = $this->mysqli->prepare("UPDATE ".$this->prefix."users SET token='$this->token', expires='$this->expires' WHERE user=?") or exit("Error in MySQL");
            $stmt->bind_param("s", $this->user) or exit("Error in MySQL");
            $stmt->execute() or exit("Error in MySQL");
            if ($this->updatePassword($password)) return $this->token;
        }
        return false;
    }
    public function compareTokens(string $token) : bool { return $token === $this->token && time() < strtotime($this->expires); }
    public function updatePassword(string $password) : bool
    {
        $pass = substr(crypt($password, "$2y$11$".str_replace("+", ".", base64_encode(random_bytes(16)))), 7);
        $stmt = $this->mysqli->prepare("UPDATE ".$this->prefix."users SET password=? WHERE user=?") or exit("Error in MySQL");
        $stmt->bind_param("ss", $pass, $this->user) or exit("Error in MySQL");
        $stmt->execute() or exit("Error in MySQL");
        return true;
    }
    public function logout() : bool
    {
        $stmt = $this->mysqli->prepare("UPDATE ".$this->prefix."users SET token='', expires='' WHERE user=?") or exit("Error in MySQL");
        $stmt->bind_param("s", $this->user) or exit("Error in MySQL");
        $stmt->execute() or exit("Error in MySQL");
        return true;
    }
    public function insertUser(string $user, string $password, int $privilege, bool $admin = false) : bool
    {
        if ((($this->admin && $admin) || ($this->privilege > 2 && $this->privilege >= $privilege) || ($this->privilege > 1 && $this->privilege > $privilege)) && $privilege > 0 && strlen($user) < 21 && ctype_alnum($user))
        {
            $st = $this->mysqli->prepare("SELECT privilege FROM ".$this->prefix."users WHERE user=?") or exit("Error in MySQL");
            $st->bind_param("s", $user) or exit("Error in MySQL");
            $st->execute() or exit("Error in MySQL");
            $query = $st->get_result() or exit("Error in MySQL");
            if ($query->num_rows === 0)
            {
                $pass = substr(crypt($password, "$2y$11$".str_replace("+", ".", base64_encode(random_bytes(16)))), 7);
                $stmt = $this->mysqli->prepare("INSERT INTO ".$this->prefix."users(user,password,privilege) VALUES (?,?,?)") or exit("Error in MySQL");
                $stmt->bind_param("ssi", $user, $pass, $privilege) or exit("Error in MySQL");
                $stmt->execute() or exit("Error in MySQL");
                $stmt->close();
                $b = true;
            }
            $query->free();
            $st->close();
            return isset($b);
        }
        return false;
    }
    public function updatePrivilege(string $user, int $privilege) : bool
    {
        if ($this->privilege === 3 && $privilege > 0 && $privilege < 4 && strlen($user) < 21 && ctype_alnum($user) && $user !== $this->user)
        {
            $stmt = $this->mysqli->prepare("UPDATE ".$this->prefix."users SET privilege=? WHERE user=?") or exit("Error in MySQL");
            $stmt->bind_param("is", $privilege, $user) or exit("Error in MySQL");
            $stmt->execute() or exit("Error in MySQL");
            $stmt->close();
            return true;
        }
        return false;
    }
    public function getPrivilege(string $user)
    {
        $stmt = $this->mysqli->prepare("SELECT privilege FROM ".$this->prefix."users WHERE user=?") or exit("Error in MySQL");
        $stmt->bind_param("s", $user) or exit("Error in MySQL");
        $stmt->execute() or exit("Error in MySQL");
        $query = $stmt->get_result() or exit("Error in MySQL");
        if ($query->num_rows === 1) $privilege = $query->fetch_row()[0];
        else $privilege = false;
        $query->free();
        $stmt->close();
        return $privilege;
    }
    public function removeUser(string $user) : bool
    {
        if (strlen($user) < 21 && ctype_alnum($user) && $user !== $this->user)
        {
            if ($this->privilege === 3)
            {
                $stmt = $this->mysqli->prepare("DELETE FROM ".$this->prefix."users WHERE user=?") or exit("Error in MySQL");
                $stmt->bind_param("s", $user) or exit("Error in MySQL");
                $stmt->execute() or exit("Error in MySQL");
                $stmt->close();
                return true;
            }
            elseif ($this->privilege === 2)
            {
                $st = $this->mysqli->prepare("SELECT privilege FROM ".$this->prefix."users WHERE user=?") or exit("Error in MySQL");
                $st->bind_param("s", $user) or exit("Error in MySQL");
                $st->execute() or exit("Error in MySQL");
                $query = $st->get_result() or exit("Error in MySQL");
                if ($query->num_rows === 1)
                {
                    $privilege = $query->fetch_row()[0];
                    if ($privilege === 1)
                    {
                        $stmt = $this->mysqli->prepare("DELETE FROM ".$this->prefix."users WHERE user=?") or exit("Error in MySQL");
                        $stmt->bind_param("s", $user) or exit("Error in MySQL");
                        $stmt->execute() or exit("Error in MySQL");
                        $stmt->close();
                        $b = true;
                    }
                }
                $query->free();
                $st->close();
                return isset($b);
            }
        }
        return false;
    }
    public function listUsers(string $page) : array
    {
        $t = "SELECT user,privilege FROM ".$this->prefix."users ORDER BY privilege DESC, user DESC LIMIT 20";
        if ($page > 1) $t .= " OFFSET ".(20*($page-1));
        $query = $this->mysqli->query($t) or exit("Error in MySQL");
        $arr = [];
        while ($element = $query->fetch_row()) { array_push($arr, [$element[0], $element[1]]); }
        return $arr;
    }
    public function insertLog(int $id, string $dir1, string $dir2 = "", string $dir3 = "") : bool
    {
        $stmt = $this->mysqli->prepare("INSERT INTO ".$this->prefix."log(user,id,dir1,dir2,dir3) VALUES(?,?,?,?,?)") or exit("Error in MySQL");
        $stmt->bind_param("sisss", $this->user, $id, $dir1, $dir2, $dir3) or exit("Error in MySQL");
        $stmt->execute() or exit("Error in MySQL");
        $stmt->close();
        return true;
    }
    public function listLog(string $page) : array
    {
        $t = "SELECT * FROM ".$this->prefix."log ORDER BY time DESC, user DESC, id DESC, dir1 DESC, dir2 DESC, dir3 DESC LIMIT 20";
        if ($page > 1) $t .= " OFFSET ".(20*($page-1));
        $query = $this->mysqli->query($t) or exit("Error in MySQL");
        $arr = [];
        while ($element = $query->fetch_row()) { array_push($arr, [$element[0], $element[1], $element[2], $element[3], $element[4], $element[5]]); }
        return $arr;
    }
}
// End of MySQL class
$access = isset($prefix) ? new Database($host, $user, $password, $database, $prefix) : new Database($host, $user, $password, $database);
session_start();
if (isset($_SESSION["user"], $_SESSION["token"]) && $access->setUser($_SESSION["user"]) && $access->compareTokens($_SESSION["token"]))
{
    ob_start();
    echo "<head><title>Filamp v.0.1.0</title></head>";
    if (isset($_POST["logout"]))
    {
        if ($access->logout())
        {
            session_destroy();
            header("Location: ?");
            exit;
        }
        else echo "Logout failed. <a href=\"?\">Go back!</a>";
    }
    elseif (isset($_GET["chpass"]))
    {
        if (isset($_POST["cpassword"], $_POST["password"], $_POST["rpassword"]))
        {
            if ($s = $access->checkLogin($_POST["cpassword"]))
            {
                $_SESSION["token"] = $s;
                if ($_POST["password"] === $_POST["rpassword"])
                {
                    if ($access->updatePassword($_POST["password"]) && session_destroy()) echo "Password changed successfully. <a href=\"?";
                    else echo "Error. ";
                }
                else echo "Passwords do not match. <a href=\"?chpass";
            }
            else echo "Current password is wrong. <a href=\"?chpass";
        }
        else echo "<form method=\"post\" action=\"?chpass\">Current password: <input type=\"password\" name=\"cpassword\" /><br />New password: <input type=\"password\" name=\"password\" /><br />Repeat password: <input type=\"password\" name=\"rpassword\" /><br /><br /><input type=\"submit\" value=\"Change password!\" /></form><a href=\"?";
        echo "\">Go back!</a>";
    }
    elseif (isset($_GET["manage"]))
    {
        if (isset($_POST["user"], $_POST["password"], $_POST["privilege"]) && $access->privilege > 1)
        {
            if ($_POST["privilege"] < 1 || $_POST["privilege"] > 3) echo "This privilege is not valid.";
            elseif (($access->privilege === 2 && $_POST["privilege"] >= 2)) echo "You can't select this privilege.";
            elseif ($access->insertUser($_POST["user"], $_POST["password"], $_POST["privilege"]) && $access->insertLog(9, $_POST["user"], $_POST["privilege"])) echo "Registered sucessfully.";
            else echo "Error.";
            echo " <a href=\"?manage\">Go back!</a>";
        }
        elseif (isset($_POST["user"], $_POST["privilege"]) && $access->privilege > 2)
        {
            $s = $access->getPrivilege($_POST["user"]);
            if ($s && $_POST["privilege"] > 0 && $_POST["privilege"] < 4 && $_POST["user"] !== $_SESSION["user"])
            {
                if ($access->updatePrivilege($_POST["user"], $_POST["privilege"]) && $access->insertLog(10, $_POST["user"], $s, $_POST["privilege"])) echo "Updated successfully.";
                else echo "Error.";
            }
            else echo "You are not able to set this privilege, or the user does not exist, or you are this user, or the privilege input is wrong.";
            echo " <a href=\"?manage\">Go back!</a>";
        }
        elseif (isset($_POST["user"]) && $access->privilege > 1)
        {
            $s = $access->getPrivilege($_POST["user"]);
            if ($s && (($access->privilege < 3 && $access->privilege > $s) || ($access->privilege > 2 && $access->privilege >= $s)) && $_POST["user"] !== $_SESSION["user"])
            {
                if ($access->removeUser($_POST["user"]) && $access->insertLog(11, $_POST["user"], $s)) echo "Success.";
                else echo "Error.";
            }
            else echo "You are not able to remove this user, or the user does not exist, or you are this user.";
            echo " <a href=\"?manage\">Go back!</a>";
        }
        else
        {
            function strPriv(int $priv) { return $priv !== 3 ? $priv !== 2 ? $priv !== 1 ? false : "Member" : "Administrator" : "Owner"; }
            $page = ctype_digit($_GET["manage"]) && $_GET["manage"] > 0 && $_GET["manage"] < 5000000000001 ? $_GET["manage"] : "1";
            echo "List of users:<br /><br /><table border=\"1\"><tr><td><strong>User</strong></td><td><strong>Privilege</strong></td></tr>";
            $i = 0;
            foreach ($access->listUsers($page) as $element)
            {
                $i++;
                echo "<tr><td>$element[0]</td><td>".strPriv($element[1])."</td></tr>";
            }
            echo "</table><br />Current page: $page<br />";
            if ($page !== "1") echo "<a href=\"?manage=".($page-1)."\">Previous page</a><br />";
            echo "<a href=\"?manage=".($page+1)."\">Next page</a><form method=\"get\" action=\"?\">Page: <input type=\"text\" name=\"manage\" /> <input type=\"submit\" value=\"Go!\" /></form>";
            if ($access->privilege > 1)
            {
                echo "<form method=\"post\" action=\"?manage\">Add member:<br />User: <input type=\"text\" name=\"user\" /><br />Password: <input type=\"password\" name=\"password\" /><br />Privilege: <select name=\"privilege\"><option value=\"1\">Member</option>";
                if ($access->privilege === 3) echo "<option value=\"2\">Administrator</option><option value=\"3\">Owner</option>";
                echo "</select><br /><input type=\"submit\" value=\"Add!\"></form>";
            }
            if ($access->privilege > 2) echo "<form method=\"post\" action=\"?manage\">Set privilege:<br />User: <input type=\"text\" name=\"user\" /><br />Privilege: <select name=\"privilege\"><option value=\"1\">Member</option><option value=\"2\">Administrator</option><option value=\"3\">Owner</option></select><br /><input type=\"submit\" value=\"Set!\"></form>";
            if ($access->privilege > 1) echo "<form method=\"post\" action=\"?manage\">Remove user: <input type=\"text\" name=\"user\" /> <input type=\"submit\" value=\"Remove!\" /></form>";
            echo "<a href=\"?\">Go back!</a>";
        }
    }
    elseif (isset($_GET["log"]))
    {
        function strPriv(int $priv) { return $priv !== 3 ? $priv !== 2 ? $priv !== 1 ? false : "Member" : "Administrator" : "Owner"; }
        function readLog(int $id, string $dir1, string $dir2, string $dir3) { return $id !== 1 ? $id !== 2 ? $id !== 3 ? $id !== 4 ? $id !== 5 ? $id !== 6 ? $id !== 7 ? $id !== 8 ? $id !== 9 ? $id !== 10 ? $id !== 11 ? false : "has removed the user $dir1 which privilege was ".strPriv($dir2) : "has set the privilege of $dir1 from ".strPriv($dir2)." to ".strPriv($dir3) : "has registered $dir1 which privilege is ".strPriv($dir2) : "has been registered as owner" : "has edited the file $dir1" : "has uploaded the file $dir1" : "has renamed the file $dir1 to $dir2" : "has copied the file $dir1 and pasted it in $dir2" : "has deleted the file $dir1" : "has created the directory $dir1" : "has removed the directory $dir1"; }
        echo "<table border=\"1\"><tr><td><strong>User</strong></td><td><strong>Action</strong></td><td><strong>Time</strong></td></tr>";
        $page = ctype_digit($_GET["log"]) && $_GET["log"] > 0 && $_GET["log"] < 5000000000001 ? $_GET["log"] : "1";
        foreach ($access->listLog($page) as $element)
        {
            echo "<tr><td>$element[0]</td><td>".readLog($element[1], $element[2], $element[3], $element[4])."</td><td>$element[5]</td></tr>";
        }
        echo "</table><br />Current page: $page<br />";
        if ($page !== "1") echo "<a href=\"?log=".($page-1)."\">Previous page</a><br />";
        echo "<a href=\"?log=".($page+1)."\">Next page</a><form method=\"get\" action=\"?\">Page: <input type=\"text\" name=\"log\" /> <input type=\"submit\" value=\"Go!\" /></form><a href=\"?\">Go back!</a>";
    }
    else
    {
        function fixDirectory(string $dir) : string
        {
            if (strlen($dir) > 300) return $_SERVER["SCRIPT_FILENAME"];
            for ($n = $dir, $dir = str_replace("../", "", $dir), $dir = str_replace("./", "", $dir), $dir = str_replace("//", "/", $dir), $dir = str_replace("\\", "/", $dir); $n !== $dir; $n = $dir)
            {
                $dir = str_replace("../", "", $dir);
                $dir = str_replace("./", "", $dir);
                $dir = str_replace("//", "/", $dir);
                $dir = str_replace("\\", "/", $dir);
            }
            while (substr($dir, -2) === "..") { $dir = substr($dir, 0, -2); }
            return str_replace("\"", "", $dir);
        }
        function isFilamp(string $dir) : bool
        {
            if (sha1_file($_SERVER["SCRIPT_FILENAME"]) === sha1_file($dir) && strpos($dir, basename($_SERVER["SCRIPT_NAME"])) !== false && filesize($_SERVER["SCRIPT_FILENAME"]) === filesize($dir)) return true;
            return false;
        }
        $cdir = $parent != 0 ? dirname(getcwd(), $parent) : getcwd();
        $get = $_POST["rmdir"] ?? $_POST["mkdir"] ?? $_POST["delete"] ?? $_POST["download"] ?? $_POST["copy"] ?? $_POST["rename"] ?? $_POST["edit"] ?? $_POST["upload"] ?? $_GET["dir"] ?? "";
        $get = fixDirectory($get);
        if (substr($get, 0, 1) !== "/") $dir = $cdir."/".$get;
        else $dir = $cdir.$get;
        $dir = fixDirectory($dir);
        if (mb_strlen($dir, "UTF-8") > 225)
        {
            echo "The length of the directory is too large. <a href=\"?\">Go back!</a>";
            exit;
        }
        if (isset($_POST["rmdir"]))
        {
            if (!in_array($get, [".", "/", ""]))
            {
                while (substr($dir, -1) === "/") { $dir = substr($dir, 0, -1); }
                $scan = scandir($dir);
                while (isset($scan[0]) && in_array($scan[0], [".", ".."])) { array_shift($scan); }
                if (!file_exists($dir) || !is_dir($dir)) echo "The directory does not exist. <a href=\"?";
                elseif (!empty($scan)) echo "The directory is not empty. <a href=\"?dir=$get";
                else
                {
                    if (rmdir($dir) && $access->insertLog(1, fixDirectory("/$get/"))) echo "Success. <a href=\"?dir=".dirname($get, 1);
                    else echo "Error. <a href=\"?dir=$get";
                }
            }
            else echo "You can't remove this directory. <a href=\"?";
            echo "\">Go back!</a>";
        }
        elseif (isset($_POST["mkdir"]))
        {
            while (substr($dir, -1) === "/") { $dir = substr($dir, 0, -1); }
            if (!file_exists(dirname($dir, 1))) echo "Main directory does not exist. <a href=\"?";
            elseif (file_exists($dir)) echo "The directory or file already exists. <a href=\"?dir=".dirname($get, 1);
            else
            {
                if (mkdir($dir) && $access->insertLog(2, fixDirectory("/$get/"))) echo "Success. <a href=\"?dir=$get";
                else echo "Error. <a href=\"?dir=".dirname($get, 1);
            }
            echo "\">Go back!</a>";
        }
        elseif (isset($_POST["delete"]))
        {
            if (is_file($dir) && file_exists($dir))
            {
                if (isFilamp($dir)) echo "You can't delete this file. <a href=\"?dir=$get";
                else
                {
                    if (unlink($dir) && $access->insertLog(3, fixDirectory("/$get"))) echo "Success. <a href=\"?dir=".dirname($get, 1);
                    else "Error. <a href=\"?dir=$get";
                }
            }
            else echo "File not found. <a href=\"?dir=$get";
            echo "\">Go back!</a>";
        }
        elseif (isset($_POST["download"]))
        {
            if (is_file($dir) && file_exists($dir))
            {
                if (isFilamp($dir)) echo "You can't download this file.";
                else
                {
                    ob_end_clean();
                    header("Content-Disposition: attachment; filename=\"".basename($dir)."\"");
                    readfile($dir);
                    exit;
                }
            }
            else echo "File not found.";
            echo " <a href=\"?dir=$get\">Go back!</a>";
        }
        elseif (isset($_POST["copy"]) || isset($_POST["rename"]))
        {
            if (isset($_POST["name"]))
            {
                $name = fixDirectory($cdir."/".$_POST["name"]);
                if (is_file($dir) && file_exists($dir))
                {
                    if (isFilamp($dir)) echo "You can't edit this file. <a href=\"?dir=$get";
                    elseif (file_exists($name)) echo "The file already exists. <a href=\"?dir=$get";
                    elseif (is_dir(dirname($name, 1)))
                    {
                        if (isset($_POST["copy"]) && copy($dir, $name) && $access->insertLog(4, fixDirectory("/$get"), fixDirectory("/".$_POST["name"]))) echo "The file has been copied and pasted. <a href=\"?dir=$get";
                        elseif (isset($_POST["rename"]) && rename($dir, $name) && $access->insertLog(5, fixDirectory("/$get"), fixDirectory("/".$_POST["name"]))) echo "The file has been renamed. <a href=\"?dir=$_POST[name]";
                        else echo "Error. <a href=\"?dir=$get\">Go back!</a>";
                    }
                    else echo "The directory was not found. <a href=\"?dir=$get";
                }
                else echo "File not found. <a href=\"?dir=$get";
            }
            else echo "The new name is missing. <a href=\"?dir=$get";
            echo "\">Go back!</a>";
        }
        elseif (isset($_POST["edit"]) || isset($_POST["upload"]))
        {
            $ndir = !isset($_POST["upload"]) ? $dir : fixDirectory($dir."/".basename($_FILES["file"]["name"]));
            if (isset($_FILES["file"]) && !empty($_FILES["file"]["name"]))
            {
                if (file_exists($ndir) && isFilamp($ndir)) echo "You can't edit this file.";
                elseif (isset($_POST["upload"]) && mb_strlen($dir."/".basename($_FILES["file"]["name"]), "UTF-8") > 225) echo "Not uploaded. The name of the file (or full directory in the server) is too large.";
                elseif (isset($_POST["upload"]) && file_exists($dir."/".basename($_FILES["file"]["name"]))) echo "Not uploaded. The file already exists.";
                else
                {
                    if (move_uploaded_file($_FILES["file"]["tmp_name"], $ndir) && ((isset($_POST["upload"]) && $access->insertLog(6, fixDirectory("/".$get."/".basename($_FILES["file"]["name"])))) || (isset($_POST["edit"]) && $access->insertLog(7, fixDirectory("/".$get))))) echo "Success.";
                    else echo "Error.";
                }
            }
            else echo "File not found. Have you uploaded a file?";
            echo " <a href=\"?dir=$get\">Go back!</a>";
        }
        else
        {
            if (is_file($dir) && file_exists($dir))
            {
                if (isFilamp($dir)) echo "You can't edit this file. <a href=\"?dir=".dirname($get, 1)."\">Go back!</a>";
                else
                {
                    list($gs, $size) = [fopen($dir, "r"), filesize($dir)];
                    if ($size < 10001)
                    {
                        list($content, $add) = [htmlspecialchars(fread($gs, filesize($dir))), false];
                        echo "Content of this file:";
                    }
                    else
                    {
                        list($content, $add) = [htmlspecialchars(fread($gs, 10000)), true];
                        echo "First 10,000 bytes of this file:";
                    }
                    $content = str_replace("<", "&gt;", $content);
                    echo "<br /><br /><textarea rows=\"10\" cols=\"50\" readonly>$content</textarea><br /><br />";
                    if ($add) echo "The file size is over 10,000 bytes. Instead of previewing the file, download it.<br /><br />";
                    echo "File size: $size B<br /><br />";
                    $dirup = dirname($get, 1)."/";
                    if (in_array($dirup, ["\\/", "//"])) $dirup = "/";
                    echo "Edit this file:<br /><br /><form method=\"post\" action=\"?\" enctype=\"multipart/form-data\"><input type=\"file\" name=\"file\" /><input type=\"hidden\" name=\"edit\" value=\"$get\" /><br /><br /><input type=\"submit\" value=\"Upload file\" /></form>Rename file (new name):<form method=\"post\" action=\"?\"><input type=\"text\" name=\"name\" value=\"$dirup\" /><input type=\"hidden\" name=\"rename\" value=\"$get\" /> <input type=\"submit\" value=\"Rename file\" /></form>Copy and paste this file to: <form method=\"post\" action=\"?\"><input type=\"text\" name=\"name\" value=\"$dirup\" /><input type=\"hidden\" name=\"copy\" value=\"$get\" /> <input type=\"submit\" value=\"Copy and paste\" /></form><form method=\"post\" action=\"?\"><a href=\"javascript:;\" onclick=\"parentNode.submit();\">Download file</a><input type=\"hidden\" name=\"download\" value=\"$get\" /></form><form method=\"post\" action=\"?\"><a href=\"javascript:;\" onclick=\"if (window.confirm('Are you sure you want to delete this file?')) parentNode.submit();\">Delete file</a><input type=\"hidden\" name=\"delete\" value=\"$get\" /></form><a href=\"?dir=".dirname($get, 1)."\">Go back!</a>";
                }
            }
            elseif (is_dir($dir) && file_exists($dir))
            {
                $scan = scandir($dir);
                while (isset($scan[0]) && in_array($scan[0], [".", ".."])) { array_shift($scan); }
                echo "<table border=\"1\"><tr><td><strong>Folder or file</strong></td><td><strong>Last modified date</strong></td><td><strong>File size</strong></td></tr>";
                if (!in_array($get, [".", "/", ""])) echo "<tr><td><a href=\"?dir=".dirname($get, 1)."\">..</a></td><td></td></tr>";
                foreach ($scan as $file)
                {
                    echo "<tr><td><a href=\"?dir=".fixDirectory($get."/".$file)."\">";
                    echo is_dir($dir."/".$file) ? $file."/" : $file;
                    echo "</a></td><td>".date("Y-m-d H:i:s", filemtime($dir."/".$file))."</td><td>".filesize($dir."/".$file)." B</td></tr>";
                }
                echo "</table><br />Upload file:<br /><br /><form method=\"post\" action=\"?\" enctype=\"multipart/form-data\"><input type=\"file\" name=\"file\" /><input type=\"hidden\" name=\"upload\" value=\"$get\" /><br /><br /><input type=\"submit\" value=\"Upload file\" /></form><form method=\"post\" action=\"?\">Create directory: <input type=\"text\" name=\"mkdir\" value=\"".fixDirectory($get."/")."\" /> <input type=\"submit\" value=\"Create\" /></form><form method=\"post\" action=\"?\"><a href=\"javascript:;\" onclick=\"if (window.confirm('Are you sure you want to remove this directory?')) parentNode.submit();\">Remove this directory</a><input type=\"hidden\" name=\"rmdir\" value=\"$get\" /></form>Welcome $_SESSION[user]!<br />Filamp v.0.1.0. <a href=\"https://github.com/Edison2ST/Filamp\">Github</a><br /><br /><a href=\"?chpass\">Change password</a><br /><a href=\"?manage\">Users</a><br /><a href=\"?log\">Log</a><br /><form method=\"post\" action=\"?\"><a href=\"javascript:;\" onclick=\"parentNode.submit();\">Logout</a><input type=\"hidden\" name=\"logout\" value=\"1\" /></form>";
            }
            else echo "Directory not found. <a href=\"?\">Go back!</a>";
        }
    }
}
elseif (isset($_POST["user"], $_POST["password"]) && $access->setUser($_POST["user"]) && $login = $access->checkLogin($_POST["password"]))
{
    $_SESSION["user"] = $_POST["user"];
    $_SESSION["token"] = $login;
    header("Location: ?");
    exit;
}
elseif (isset($GLOBALS["admin"], $_GET["new"]) && $GLOBALS["admin"])
{
    if (isset($_POST["user"], $_POST["password"]))
    {
        if ($access->insertUser($_POST["user"], $_POST["password"], 3, true) && $access->setUser($_POST["user"]) && $access->insertLog(8, "")) echo "Registered succesfully. <a href=\"?\">Go back!</a>";
        else echo "Error. <a href=\"?new\">Go back!";
    }
    else echo "<form method=\"post\" action=\"?new\">User: <input type=\"text\" name=\"user\" /><br />Password: <input type=\"password\" name=\"password\" /><br /><br /><input type=\"submit\" value=\"Register\" /></form>";
}
else
{
    echo "<form method=\"post\" action=\"?\">User: <input type=\"text\" name=\"user\" /><br />Password: <input type=\"password\" name=\"password\" /><br /><br /><input type=\"submit\" value=\"Login\" /></form>";
    if (isset($_POST["user"], $_POST["password"])) echo "Wrong user or password!";
}
?>