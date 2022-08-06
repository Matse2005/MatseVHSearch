<?php
// Set the required headers for the API to work (POST)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include the database and the push model
include_once '../../database.php';

// Check if the request method is POST
if (!isset($_POST)) {
  // If not, return a 400 error
  http_response_code(400);
  echo json_encode(array("message" => "Bad request", "specific_message" => "The request method must be POST"));
  exit();
}

// Get the data from the POST request
$data = $_POST;

// Check if the data is valid
if (!isset($data["index"]) || !isset($data["query"]) || !isset($data["app_id"]) || !isset($data["key"])) {
  // If not, return a 400 error
  http_response_code(400);
  echo json_encode(array("message" => "Bad request", "specific_message" => "The request must contain the following parameters: index, query, app_id, key"));
  exit();
}

// Check if the API key is valid
// Hash the api key with the app id
$key_hash = hash('sha256', $data["key"] . $data["app_id"]);

$stmt = $pdo->prepare("SELECT * FROM api_keys WHERE id = :app_id AND token = :key");
$stmt->bindParam(":app_id", $data["app_id"]);
$stmt->bindParam(":key", $key_hash);
$stmt->execute();
$key = $stmt->fetch();
if (!$key) {
  // If not, return a 401 error
  http_response_code(401);
  echo json_encode(array("message" => "Unauthorized", "specific_message" => "The API key is invalid"));
  exit();
}
if ($key["enabled"] == 0) {
  // If not, return a 401 error
  http_response_code(401);
  echo json_encode(array("message" => "Unauthorized", "specific_message" => "The API key is disabled"));
  exit();
}

// Check if folder exists
if (!file_exists($_SERVER["DOCUMENT_ROOT"] . "/documents/" . $data["app_id"])) {
  // If not, create it
  mkdir($_SERVER["DOCUMENT_ROOT"] . "/documents/" . $data["app_id"]);
}

// Check if file exists
if (!file_exists($_SERVER["DOCUMENT_ROOT"] . "/documents/" . $data["app_id"] . "/" . $data["index"] . ".json")) {
  // If not, return a 404 error
  http_response_code(404);
  echo json_encode(array("message" => "Index not found"));
  exit();
}

// Split the query into words
$words = explode(" ", $data["query"]);

// Open the file
$file = fopen($_SERVER["DOCUMENT_ROOT"] . "/documents/" . $data["app_id"] . "/" . $data["index"] . ".json", "r");
// Read the file
$json = fread($file, filesize($_SERVER["DOCUMENT_ROOT"] . "/documents/" . $data["app_id"] . "/" . $data["index"] . ".json"));
// Close the file
fclose($file);

// Decode the json
$objects = json_decode($json, true);

// Search the json for the words
$results = array();
foreach ($objects as $object) {
  $found = false;

  foreach ($words as $word) {
    foreach ($object as $search) {
      if (search($search, $word)) {
        $found = true;
        break;
      }
    }
  }

  if ($found) {
    $results[] = $object;
  }
}

// Return a 200 success
http_response_code(200);
echo json_encode(array("message" => "Success", "objects" => $results));
exit();

function search($item, $word)
{
  if (is_array($item)) {
    foreach ($item as $key => $value) {
      if (search($value, $word)) {
        return true;
      }
    }
  } else {
    if (strpos($item, $word) !== false) {
      return true;
    }
  }

  return false;
}
