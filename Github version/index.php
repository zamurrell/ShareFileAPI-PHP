<!DOCTYPE HTML>
<html>

<head>
</head>

<body>

    <?php

    include_once __DIR__ . '/vendor/autoload.php';

    // $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    var_dump(getenv('PROJECT_NAME'));
    var_dump($_ENV['PROJECT_NAME']);

    $hostname = "";
    $username = "";
    $redirect_uri = "";

    /**
     * Authenticate via username/password. Returns json token object.
     *
     * @param string $hostname - hostname like "myaccount.sharefile.com"
     * @param string $client_id - OAuth2 client_id key
     * @param string $client_secret - OAuth2 client_secret key
     * @param string $username - my@user.name
     * @param string $password - my password
     * @return json token
     */
    function authenticate($hostname, $client_id, $client_secret, $username, $password)
    {
        $uri = "https://" . $hostname . "/oauth/token";
        echo "POST " . $uri . "\n";

        $body_data = array(
            "grant_type" => "password", "client_id" => $client_id, "client_secret" => $client_secret,
            "username" => $username, "password" => $password
        );
        $data = http_build_query($body_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/x-www-form-urlencoded'));

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // echo "http code: " . $http_code;

        // $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        // echo " curl_response:" . $curl_response . "\n"; // output entire response
        echo "http code: " . $http_code . "\n"; // output http status code

        curl_close($ch);
        $token = NULL;
        if ($http_code == 200) {
            echo "Token successfully retrieved ";
            $token = json_decode($curl_response);
            //print_r($token); // print entire token object
        }

        return $token;
    }

    function get_authorization_header($token)
    {
        return array("Authorization: Bearer " . $token->access_token);
    }

    function get_hostname($token)
    {
        return $token->subdomain . ".sf-api.com";
    }

    /**
     * Get the root level Item for the provided user. To retrieve Children the $expand=Children
     * parameter can be added.
     *
     * @param string $token - json token acquired from authenticate function
     * @param boolean $get_children - retrieve Children Items if True, default is FALSE
     */
    function get_root($token, $get_children = FALSE)
    {
        $uri = "https://" . get_hostname($token) . "/sf/v3/Items";
        if ($get_children == TRUE) {
            $uri .= "?\$expand=Children";
        }
        echo "GET " . $uri . "\n";

        $headers = get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        echo "http code: " . $http_code . "\n"; // output http status code

        curl_close($ch);

        $root = json_decode($curl_response);
        //print_r($root); // print entire json response
        echo "Root ID: " . $root->Id . " Root Creation Date: " . $root->CreationDate . " Root Name: " . $root->Name . " Root Folder: " . $root->Folder . "\n";
        if (property_exists($root, "Children")) {
            foreach ($root->Children as $child) {
                echo "Child information: " . $child->Id . " " . $child->CreationDate . " " . $child->Name . "\n";
            }
        }
    }

    /**
     * Create a new folder in the given parent folder.
     *
     * @param string $token - json token acquired from authenticate function
     * @param string $parent_id - the parent folder in which to create the new folder
     * @param string $name - the folder name
     * @param string $description - the folder description
     */
    function create_folder($token, $parent_id, $name, $description)
    {
        $uri = "https://" . get_hostname($token) . "/sf/v3/Items(" . $parent_id . ")/Folder";
        echo "POST " . $uri . "\n";

        $folder = array("Name" => $name, "Description" => $description);
        $data = json_encode($folder);

        $headers = get_authorization_header($token);
        $headers[] = "Content-Type: application/json";
        print_r($headers);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        echo $http_code . "\n"; // output http status code

        curl_close($ch);

        if ($http_code == 200) {
            $item = json_decode($curl_response);
            print_r($item); // print entire new item object
            echo "Created Folder: " . $item->Id . "\n";
        }
    }

    /**
     * Get the Client users in the Account.
     * 
     * @param string $token - json token acquired from authenticate function
     */
    function get_clients($token)
    {
        $uri = "https://" . get_hostname($token) . "/sf/v3/Accounts/GetClients";
        echo "GET " . $uri . "\n";

        $headers = get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        echo "http code: " . $http_code . "\n"; // output http status code

        curl_close($ch);

        $clients = json_decode($curl_response);
        //print_r($response); // print entire json response
        if ($clients->value != NULL) {
            foreach ($clients->value as $client) {
                echo "Client ID: " . $client->Id . " Client Email: " . $client->Email . "\n";
            }
        }
    }

    /**
     * Create a Client user in the Account.
     *
     * @param string $token - json token acquired from authenticate function
     * @param string $email - email address of the new user
     * @param string $firstname - firsty name of the new user
     * @param string $lastname - last name of the new user
     * @param string $company - company of the new user
     * @param string $clientpassword - password of the new user
     * @param boolean $canresetpassword - user preference to allow user to reset password
     * @param boolean $canviewmysettings - user preference to all user to view 'My Settings'
     */
    function create_client(
        $token,
        $email,
        $firstname,
        $lastname,
        $company,
        $clientpassword,
        $canresetpassword,
        $canviewmysettings
    ) {

        $uri = "https://" . get_hostname($token) . "/sf/v3/Users";
        echo "POST " . $uri . "\n";

        $client = array(
            "Email" => $email, "FirstName" => $firstname, "LastName" => $lastname, "Company" => $company,
            "Password" => $clientpassword,
            "Preferences" => array("CanResetPassword" => $canresetpassword, "CanViewMySettings" => $canviewmysettings)
        );
        $data = json_encode($client);

        $headers = get_authorization_header($token);
        $headers[] = "Content-Type: application/json";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        echo "http_code: " . $http_code . "\n"; // output http status code

        curl_close($ch);

        if ($http_code == 200) {
            $client = json_decode($curl_response);
            print_r($client); // print entire new item object
            echo "Created Client: " . $client->Id . "\n";
        }
    }

    /**
     * Create a Share.
     *
     */
    function create_share(
        $token,
        $type,
        $title,
        $item_id,
        $recipients,
        $expirationdate,
        $requirelogin,
        $requireuserinfo,
        $maxdownloads,
        $usesstreamids
    ) {

        $uri = "https://" . get_hostname($token) . "/sf/v3/Shares?notify=false";
        echo "POST " . $uri . "\n";

        $share = array(
            'ShareType' => $type,
            'Title' => $title,
            'Items' =>
            array(
                0 =>
                array(
                    'Id' => $item_id,
                ),
            ),
            'Recipients' =>
            array(
                0 =>
                array(
                    'User' =>
                    array(
                        'Id' => $recipients,
                    ),
                ),
            ),
            'ExpirationDate' => $expirationdate,
            'RequireLogin' => $requirelogin,
            'RequireUserInfo' => $requireuserinfo,
            'MaxDownloads' => $maxdownloads,
            'UsesStreamIDs' => $usesstreamids,
        );


        $data = json_encode($share);
        echo "<br>Data for share: " . $data;

        $headers = get_authorization_header($token);
        $headers[] = "Content-Type: application/json";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        echo " http_code: " . $http_code . "\n"; // output http status code

        curl_close($ch);

        if ($http_code == 200) {
            $share = json_decode($curl_response);
            echo "<h4 style='margin-bottom:0;'>Print Created Share</h4>";
            print_r($share); // print entire new item object
        }
    }


    /**
     * Send email with created share
     *
     */
    function deliver_email(
        $token,
        $email_address,
        $subject,
        $item_id,
        $body,
        $ccsender,
        $notifyondownload,
        $requirelogin,
        $maxdownloads,
        $expirationdays
    ) {

        $uri = "https://" . get_hostname($token) . "/sf/v3/Shares/Send";
        echo "POST " . $uri . "\n";

        $email = array(
            'Items' =>
            array(
                0 => $item_id,
                //   1 => 'itemId2',
            ),
            'Emails' =>
            array(
                0 => $email_address,
                //   1 => 'email2@sharefile.com',
            ),
            'Subject' => $subject,
            'Body' => $body,
            'CcSender' => $ccsender,
            'NotifyOnDownload' => $notifyondownload,
            'RequireLogin' => $requirelogin,
            'MaxDownloads' => $maxdownloads,
            'ExpirationDays' => $expirationdays,
        );


        $data = json_encode($email);
        echo "<br>Data for email: " . $data;

        $headers = get_authorization_header($token);
        $headers[] = "Content-Type: application/json";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        echo " http_code: " . $http_code . "\n"; // output http status code

        curl_close($ch);

        if ($http_code == 200) {
            $email = json_decode($curl_response);
            echo "<h4 style='margin-bottom:0;'>Print Created Email</h4>";
            print_r($email); // print entire new item object
        }
    }

    /**
     * Uploads a File using the Standard upload method with a multipart/form mime encoded POST.
     * 
     * @param string $token - json token acquired from authenticate function
     * @param string $folder_id - where to upload the file
     * @param string $local_path - the full path of the file to upload, like "c:\\path\\to\\file.name"
     */
    function upload_file($token, $folder_id, $local_path)
    {
        $uri = "https://" . get_hostname($token) . "/sf/v3/Items(" . $folder_id . ")/Upload";
        echo "GET " . $uri . "\n";

        $headers = get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        echo $http_code;

        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        $upload_config = json_decode($curl_response);

        $fmt = "&fmt=json";

        if ($http_code == 200) {
            $post["File1"] = new CurlFile($local_path);
            curl_setopt($ch, CURLOPT_URL, $upload_config->ChunkUri . $fmt);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);

            $upload_response = curl_exec($ch);

            // echo $upload_response['id'];
            echo " UPLOAD RESPONSE: " . $upload_response . "\n";
        }
        curl_close($ch);
    }

    echo "<h4 style='margin-bottom:0;'>Authenticate</h4>";
    $token = authenticate($hostname, $_ENV['CLIENT_ID'], $_ENV['CLIENT_SECRET'], $username, $_ENV['PASSWORD']);
    echo "<h4 style='margin-bottom:0;'>Token</h4>";
    print_r($token);
    if ($token) {
        echo "<h4 style='margin-bottom:0;'>Root & Children</h4>";
        get_root($token, TRUE);
    }

    $local_path = "/Users/zacharymurrell/Desktop/ShareFilePOC/post_content.txt";
    $root_folder_id = "fohe048c-f5c0-4fdf-bd51-ca953cee3243";

    echo "<h4 style='margin-bottom:0;'>Upload</h4>";
    upload_file($token, $root_folder_id, $local_path);

    $client_email = "";
    $client_firstname = "";
    $client_lastname = "";
    $client_company = "";
    $client_password = "12345";
    $client_canresetpassword = true;
    $client_canviewmysettings = true;

    echo "<h4 style='margin-bottom:0;'>Create Client</h4>";
    create_client(
        $token,
        $client_email,
        $client_firstname,
        $client_lastname,
        $client_company,
        $client_clientpassword,
        $client_canresetpassword,
        $client_canviewmysettings
    );

    echo "<h4 style='margin-bottom:0;'>Get Clients</h4>";
    get_clients($token);

    $share_type = "Send";
    $share_title = "Test Send Share";
    $share_item_id = "fi9bf92b-082d-09c4-f82d-93363a96cfac";
    $share_recipients = "6ffe1573-afde-4841-b948-f2167858f8ae";
    $share_expirationdate = "2020-10-23";
    $share_requirelogin = false;
    $share_requireuserinfo = false;
    $share_maxdownloads = -1;
    $share_usesstreamids = false;

    echo "<h4 style='margin-bottom:0;'>Create Share</h4>";
    create_share(
        $token,
        $share_type,
        $share_title,
        $share_item_id,
        $share_recipients,
        $share_expirationdate,
        $share_requirelogin,
        $share_requireuserinfo,
        $share_maxdownloads,
        $share_usesstreamids,
    );

    $email_address = "";
    $subject = "Test email subject";
    $item_id = "";
    $body = "This is the email's body text. Click below to download your document.";
    $ccsender = false;
    $notifyondownload = true;
    $requirelogin = false;
    $maxdownloads = 30;
    $expirationdays = -1;


    echo "<h4 style='margin-bottom:0;'>Send Email</h4>";
    deliver_email(
        $token,
        $email_address,
        $subject,
        $item_id,
        $body,
        $ccsender,
        $notifyondownload,
        $requirelogin,
        $maxdownloads,
        $expirationdays
    )

    ?>
</body>

</html>