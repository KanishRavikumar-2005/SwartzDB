<?php
require_once "config.php";

function Encrypt($plaintext, $cipher, $key, $iv) {
    return openssl_encrypt($plaintext, $cipher, $key, $options = 1, $iv);
}
function Decrypt($cyp, $cipher, $key, $iv) {
    return openssl_decrypt($cyp, $cipher, $key, $options = 1, $iv);
}


function reportError($error) {
    echo "<script>console.error('$error')</script>";
}
function reportWarn($error) {
    echo "<script>console.error('$error')</script>";
}

function array_intersect_recursive($array1, $array2) {
    $result = [];
    foreach ($array2 as $key => $value) {
        if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
            $intersect = array_intersect_recursive($array1[$key], $value);
            if (!empty($intersect)) {
                $result[$key] = $intersect;
            }
        } elseif (isset($array1[$key]) && $array1[$key] === $value) {
            $result[$key] = $value;
        }
    }
    return $result;
}

function refer_to($object, $alias) {
    global $$alias;
    $$alias = function ($method, ...$args) use ($object) {
        return $object->$method(...$args);
    };
}

function applyHtmlEntities($value) {
    if (is_array($value)) {
        return array_map('applyHtmlEntities', $value);
    } else {
        return htmlentities($value, ENT_QUOTES, 'UTF-8');
    }
}

class SwartzDB {
    private $path;

    public function __construct($path = null) {
        $this->path = $path ?? $_SERVER['DOCUMENT_ROOT'] . '/SwartzDB/storage/';
    }
    
    public function refresh() {
        echo "<script>window.location.assign(window.location.href)</script>";
    }

  
    public function create($jsdbname, $error_report = false) {
        try {
            $directory = rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR; // Ensure proper directory structure
    
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                    throw new Exception("Failed to create directory: $directory");
                }
            }
    
            $filePath = $directory . "$jsdbname.sdb";
            $jsdbf = fopen($filePath, "w"); // Create the empty file
            if ($jsdbf === false) {
                throw new Exception("Failed to create file: $filePath");
            }
            fclose($jsdbf);
        } catch (Exception $e) {
            $errorMessage = "An error occurred while creating the database: " . $e->getMessage();
            if ($error_report) {
                reportError($errorMessage);
            }
        }
    }
    
    

    public function aggregate(array $arr, string $operation) {
        if (empty($arr)) {
            return null; 
        }
    
        switch (strtoupper($operation)) {
            case 'SUM':
                return array_sum($arr);
            case 'MIN':
                return min($arr);
            case 'MAX':
                return max($arr);
            case 'AVG':
                return array_sum($arr) / count($arr);
            case 'COUNT':
                return count($arr);
            default:
                throw new InvalidArgumentException("Unsupported aggregate function: $operation");
        }
    }

    function filter($arrays, $filterKeys) {
        $result = [];
    
        foreach ($arrays as $array) {
            $filtered = [];
    
            foreach ($filterKeys as $key => $operation) {
                if (is_numeric($key)) {  
                    if (isset($array[$operation])) {
                        $filtered[$operation] = $array[$operation];
                    }
                } elseif (is_array($operation)) {
                    foreach ($operation as $opName => $params) {
                        switch ($opName) {
                            case 'concat':
                                $filtered[$key] = implode('', array_map(
                                    fn($param) => str_starts_with($param, 's::') 
                                        ? substr($param, 3) 
                                        : strval($array[$param] ?? ''), 
                                    $params
                                ));
                                break;
    
                            case 'uppercase':
                                $filtered[$key] = strtoupper(
                                    str_starts_with($params, 's::') 
                                        ? substr($params, 3) 
                                        : ($array[$params] ?? '')
                                );
                                break;
    
                            case 'lowercase':
                                $filtered[$key] = strtolower(
                                    str_starts_with($params, 's::') 
                                        ? substr($params, 3) 
                                        : ($array[$params] ?? '')
                                );
                                break;
    
                            case 'sum':
                                $filtered[$key] = array_sum(array_map(
                                    fn($param) => floatval(
                                        str_starts_with($param, 's::') 
                                            ? substr($param, 3) 
                                            : ($array[$param] ?? 0)
                                    ),
                                    $params
                                ));
                                break;
    
                            case 'difference':
                                $filtered[$key] = array_reduce(
                                    $params, 
                                    fn($carry, $param) => $carry - floatval(
                                        str_starts_with($param, 's::') 
                                            ? substr($param, 3) 
                                            : ($array[$param] ?? 0)
                                    ),
                                    floatval(str_starts_with($params[0], 's::') 
                                        ? substr($params[0], 3) 
                                        : ($array[$params[0]] ?? 0))
                                );
                                break;
    
                            case 'date_format':
                                $dateValue = str_starts_with($params[0], 's::') 
                                    ? substr($params[0], 3) 
                                    : ($array[$params[0]] ?? '');
                            
                                if (!empty($dateValue)) {
                                    $dateFormats = [
                                        'Y-m-d',        
                                        'd/m/Y',        
                                        'm/d/Y',        
                                        'd-m-Y',        
                                        'M d, Y',        
                                        'd M Y',         
                                        'd F Y',         
                                        'j M Y',        
                                        'j F Y',         
                                        'M j, Y',        
                                    ];
                            
                                    $dateObject = false;
                                    foreach ($dateFormats as $format) {
                                        $dateObject = DateTime::createFromFormat($format, $dateValue);
                                        if ($dateObject) {
                                            break;
                                        }
                                    }
                            
                                    if ($dateObject) {
                                        $filtered[$key] = $dateObject->format($params[1]);
                                    } else {
                                        $filtered[$key] = $dateValue;  
                                    }
                                }
                                break;
                            default:
                                return [];
                                
                        }
                    }
                }
            }
    
            $result[] = $filtered;
        }
    
        return $result;
    }
    
    private function safeWrite($file, $data) {
        $fp = fopen($file, 'w');
        if (flock($fp, LOCK_EX)) { 
            fwrite($fp, $data);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public function get($file, $form = "", $error_report = false)  {

        try {
            $cipher_gt = $GLOBALS["cipher"];
            $key_gt = $GLOBALS["key"];
            $ekey_gt = $GLOBALS["Ekey"];
            $iv_gt = $GLOBALS["iv"];
            $get_data = file_get_contents($this->path.$file . ".sdb");
            if ($get_data === false) {
                $errorMessage = "Failed to read the database file.";
                if ($error_report) {
                    reportError($errorMessage);
                }
                return null;
            }
            $scrypt = Decrypt($get_data, $cipher_gt, $key_gt, $iv_gt);
            $decrypt = Decrypt($scrypt, $cipher_gt, $ekey_gt, $iv_gt);
            $result = json_decode($decrypt, true);
            if (empty($result)) {
                $warningMessage = "The database is empty.";
                if ($error_report) {
                    reportWarn($warningMessage);
                }
                return [];
            } else {
                if ($form == "") {
                    return $result;
                } elseif ($form == "reverse") {
                    return array_reverse($result);
                }
            }
        }
        catch(Exception $e) {
            $errorMessage = "An error occurred while retrieving the data: " . $e->getMessage();
            if ($error_report) {
                reportError($errorMessage);
            }
            return null;
        }
    }
    public function put($file, $content, $error_report = false) {

        try {
            $cipher_gt = $GLOBALS["cipher"];
            $key_gt = $GLOBALS["key"];
            $ekey_gt = $GLOBALS["Ekey"];
            $iv_gt = $GLOBALS["iv"];
            $ycrypt = Encrypt($content, $cipher_gt, $ekey_gt, $iv_gt);
            $encrypt = Encrypt($ycrypt, $cipher_gt, $key_gt, $iv_gt);
            $result = $this->safeWrite($this->path.$file . ".sdb", $encrypt);
            if ($result === false) {
                $errorMessage = "Failed to write data to the database.";
                if ($error_report) {
                    reportError($errorMessage);
                }
            }
        }
        catch(Exception $e) {
            $errorMessage = "An error occurred while writing data to the database: " . $e->getMessage();
            if ($error_report) {
                reportError($errorMessage);
            }
        }
    }
    public function idgen($length = 10, $delim = "") {
        $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $charactersLength = strlen($characters);
        $randomString = "";
        for ($i = 0;$i < $length;$i++) {
            $randomString.= $characters[rand(0, $charactersLength - 1) ] . $delim;
        }
        return $randomString;
    }
    public function safe($value) {
        $value = htmlentities($value);
        return $value;
    }
    public function remove_row($file, $conditions) {
        $data = $this->get($file);
        $newData = [];
    
        function evaluateConditions($row, $conditions, $logicalOperator = 'AND') {
            if (!is_array($conditions)) {
                return false;
            }
    
            $match = ($logicalOperator === 'AND');
    
            foreach ($conditions as $key => $condition) {
                if ($key === "AND" || $key === "OR") {
                    $subConditions = $condition;
                    $subMatch = ($key === "AND");
    
                    foreach ($subConditions as $subCondition) {
                        $result = evaluateConditions($row, $subCondition, 'AND');
                        if ($key === "AND") {
                            $subMatch = $subMatch && $result;
                            if (!$subMatch) break;  
                        } else {  
                            $subMatch = $subMatch || $result;
                            if ($subMatch) break;  
                        }
                    }
    
                    if ($logicalOperator === "AND") {
                        $match = $match && $subMatch;
                        if (!$match) break;  
                    } else { 
                        $match = $match || $subMatch;
                        if ($match) break;  
                    }
                } else {
                    $conditionMatch = true;
    
                    if (is_array($condition)) {
                        [$operator, $value] = $condition;
    
                        switch ($operator) {
                            case '>':
                                $conditionMatch = isset($row[$key]) && $row[$key] > $value;
                                break;
                            case '<':
                                $conditionMatch = isset($row[$key]) && $row[$key] < $value;
                                break;
                            case '>=':
                                $conditionMatch = isset($row[$key]) && $row[$key] >= $value;
                                break;
                            case '<=':
                                $conditionMatch = isset($row[$key]) && $row[$key] <= $value;
                                break;
                            case '!=':
                                $conditionMatch = isset($row[$key]) && $row[$key] != $value;
                                break;
                            case '==':  
                            default:
                                $conditionMatch = isset($row[$key]) && $row[$key] == $value;
                                break;
                        }
                    } else {
                        $conditionMatch = isset($row[$key]) && $row[$key] == $condition;
                    }
    
                    if ($logicalOperator === "AND") {
                        $match = $match && $conditionMatch;
                        if (!$match) break;  
                    } else {  
                        $match = $match || $conditionMatch;
                        if ($match) break;  
                    }
                }
            }
    
            return $match;
        }
    
        foreach ($data as $row) {
            if (!evaluateConditions($row, $conditions, "AND")) {
                $newData[] = $row;  
            }
        }
    
        $this->put($file, json_encode(array_values($newData)));
    }
    
    
    public function update_row($file, $conditions, $newValues) {
        $data = $this->get($file);
    
        
        function evaluateConditions($row, $conditions, $logicalOperator = 'AND') {
            if (!is_array($conditions)) {
                return false;
            }
    
            $match = ($logicalOperator === 'AND');
    
            foreach ($conditions as $key => $condition) {
                if ($key === "AND" || $key === "OR") {
                    $subConditions = $condition;
                    $subMatch = ($key === "AND");
    
                    foreach ($subConditions as $subCondition) {
                        $result = evaluateConditions($row, $subCondition, 'AND');
                        if ($key === "AND") {
                            $subMatch = $subMatch && $result;
                            if (!$subMatch) break; 
                        } else {  
                            $subMatch = $subMatch || $result;
                            if ($subMatch) break;  
                        }
                    }
    
                    if ($logicalOperator === "AND") {
                        $match = $match && $subMatch;
                        if (!$match) break; 
                    } else {  
                        $match = $match || $subMatch;
                        if ($match) break; 
                    }
                } else {
                    $conditionMatch = true;
    
                    if (is_array($condition)) {
                        [$operator, $value] = $condition;
    
                        switch ($operator) {
                            case '>':
                                $conditionMatch = isset($row[$key]) && $row[$key] > $value;
                                break;
                            case '<':
                                $conditionMatch = isset($row[$key]) && $row[$key] < $value;
                                break;
                            case '>=':
                                $conditionMatch = isset($row[$key]) && $row[$key] >= $value;
                                break;
                            case '<=':
                                $conditionMatch = isset($row[$key]) && $row[$key] <= $value;
                                break;
                            case '!=':
                                $conditionMatch = isset($row[$key]) && $row[$key] != $value;
                                break;
                            case '==':  
                            default:
                                $conditionMatch = isset($row[$key]) && $row[$key] == $value;
                                break;
                        }
                    } else {
                        $conditionMatch = isset($row[$key]) && $row[$key] == $condition;
                    }
    
                    if ($logicalOperator === "AND") {
                        $match = $match && $conditionMatch;
                        if (!$match) break;  
                    } else { 
                        $match = $match || $conditionMatch;
                        if ($match) break;  
                    }
                }
            }
    
            return $match;
        }
    
        foreach ($data as $key => $row) {
            if (evaluateConditions($row, $conditions, "AND")) {
                foreach ($newValues as $updateField => $updateValue) {
                    $data[$key][$updateField] = $updateValue;
                }
            }
        }
    
        $this->put($file, json_encode(array_values($data)));
    }
    
    
    public function add_row($file, $code) {
        $givnData = $code;
        $dataV = $this->get($file);
        $dataV[] = $givnData;
        $final = json_encode($dataV);
        $this->put($file, $final);
    }
    public function addJsonDirect($file, ...$code) {
        $dataV = $this->get($file);
        if (count($code) === 1 && is_array($code[0])) {
            $code = $code[0];
        }
        $dataV = array_merge($dataV, $code);
        $final = json_encode($dataV);
        $this->put($file, $final);
    }
    public function get_row($file, $conditions, $form = "", $error_report = false) {
        $recvDD = $this->get($file);
        $filteredData = [];
    
       
        function evaluateConditions($row, $conditions, $logicalOperator = 'AND') {
            if (!is_array($conditions)) {
                return false;
            }
    
            $match = ($logicalOperator === 'AND');  
    
            foreach ($conditions as $key => $condition) {
                 
                if ($key === "AND" || $key === "OR") {
                    $subConditions = $condition;
                    $subMatch = ($key === "AND");
    
                    foreach ($subConditions as $subCondition) {
                        $result = evaluateConditions($row, $subCondition, 'AND');  
                        if ($key === "AND") {
                            $subMatch = $subMatch && $result;
                            if (!$subMatch) break;  
                        } else {  
                            $subMatch = $subMatch || $result;
                            if ($subMatch) break;  
                        }
                    }
    
                    if ($logicalOperator === "AND") {
                        $match = $match && $subMatch;
                        if (!$match) break;  
                    } else {  
                        $match = $match || $subMatch;
                        if ($match) break;  
                    }
                } else {
                     
                    $conditionMatch = true;
    
                    if (is_array($condition)) {
                        [$operator, $value] = $condition;
    
                        switch ($operator) {
                            case '>':
                                $conditionMatch = isset($row[$key]) && $row[$key] > $value;
                                break;
                            case '<':
                                $conditionMatch = isset($row[$key]) && $row[$key] < $value;
                                break;
                            case '>=':
                                $conditionMatch = isset($row[$key]) && $row[$key] >= $value;
                                break;
                            case '<=':
                                $conditionMatch = isset($row[$key]) && $row[$key] <= $value;
                                break;
                            case '!=':
                                $conditionMatch = isset($row[$key]) && $row[$key] != $value;
                                break;
                            case '==':  
                            default:
                                $conditionMatch = isset($row[$key]) && $row[$key] == $value;
                                break;
                        }
                    } else {
                         
                        $conditionMatch = isset($row[$key]) && $row[$key] == $condition;
                    }
    
                   
                    if ($logicalOperator === "AND") {
                        $match = $match && $conditionMatch;
                        if (!$match) break;  
                    } else {  
                        $match = $match || $conditionMatch;
                        if ($match) break;  
                    }
                }
            }
    
            return $match;
        }
    
        
        foreach ($recvDD as $row) {
            if (evaluateConditions($row, $conditions, "AND")) {
                $filteredData[] = $row;
            }
        }
    
        if (empty($filteredData)) {
            if ($error_report) {
                reportWarn("No matching rows found.");
            }
            return [];
        }
    
        return ($form === "reverse") ? array_reverse($filteredData) : $filteredData;
    }
    
    
    public function getKeys($file) {
        $result = $this->get($file);
        if ($result === null) {
            return [];
        } else {
            $keys = [];
            $removeDuplicatesRecursive = function ($array, $parentKey = '') use (&$removeDuplicatesRecursive, &$keys) {
                foreach ($array as $key => $value) {
                    $currentKey = ($parentKey !== '') ? $parentKey . '[' . $key . ']' : $key;
                    if (is_array($value)) {
                        $removeDuplicatesRecursive($value, $currentKey);
                    } else {
                        $keys[] = $currentKey;
                    }
                }
            };
            $removeDuplicatesRecursive($result);
            $lastArrayKeys = array_values(array_unique(array_slice($keys, -count($result))));
            $nka = $this->displayInputArray($lastArrayKeys);
            return $nka;
        }
    }
    private function displayInputArray($inputArray) {
        $result = [];
        foreach ($inputArray as $item) {
            preg_match('/^\d+\[(.*?)\]$/', $item, $matches);
            if (isset($matches[1])) {
                $keys = explode('][', $matches[1]);
                $nestedArray = & $result;
                foreach ($keys as $key) {
                    if (!isset($nestedArray[$key])) {
                        $nestedArray[$key] = [];
                    }
                    $nestedArray = & $nestedArray[$key];
                }
            }
        }
        return $result;
    }
    public function decall($file, $tofl)
    {
        $cipher_gt = $GLOBALS["cipher"];
        $key_gt = $GLOBALS["key"];
      $ekey_gt = $GLOBALS["Ekey"];
        $iv_gt = $GLOBALS["iv"];
        $get_data = file_get_contents($this->path.$file . ".sdb");

        if ($get_data === false) {
            $errorMessage = "Failed to read the database file.";
            return null;
        }

        $decrypt = Decrypt($get_data, $cipher_gt, $key_gt, $iv_gt);
        $scrypt = Decrypt($decrypt, $cipher_gt, $ekey_gt, $iv_gt);
        $result = $this->safeWrite($this->path.$tofl . ".json", $scrypt);

        if ($result === false) {
            $errorMessage =
                "Failed to write decrypted data to the destination file.";
        }
    }

    public function encall($file, $tofl)
    {
        $cipher_gt = $GLOBALS["cipher"];
        $key_gt = $GLOBALS["key"];
        $ekey_gt = $GLOBALS["Ekey"];
        $iv_gt = $GLOBALS["iv"];
        $content = file_get_contents($this->path.$file . ".json");

        if ($content === false) {
            $errorMessage = "Failed to read the source file.";
            return null;
        }

        $encrypt = Encrypt($content, $cipher_gt, $ekey_gt, $iv_gt);
        $scrypt = Encrypt($encrypt, $cipher_gt, $key_gt, $iv_gt);
        $result = $this->safeWrite($this->path.$tofl . ".sdb", $scrypt);

        if ($result === false) {
            $errorMessage =
                "Failed to write encrypted data to the destination file.";
        }
    }

    public function integrity($file) {
        $structuredData = $this->get($file);  
        $rawData = file_get_contents($this->path . $file . ".sdb");  
        
        if (empty($structuredData) && !empty($rawData)) {
            return false;  
        }
        return true;  
    }
    
    public function backup($file, $customName = null, $backupFolder = "backup") {
        $rawData = file_get_contents($this->path . $file . ".sdb");
    
        if (!empty($rawData)) {
            $backupDir = $this->path . rtrim($backupFolder, "/") . "/";
    
             
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0777, true);
            }
    
            
            $backupName = $customName ?? $file . "." . time();
            $backupPath = $backupDir . $backupName . ".bak";
    
            file_put_contents($backupPath, $rawData);
        }
    }
    public function restore($backupFile, $backupFolder = "backup", $originalFile = null) {
        $backupPath = $this->path . rtrim($backupFolder, "/") . "/" . $backupFile;
        
         
        if (!file_exists($backupPath)) {
            return false;  
        }
    
         
        $originalFile = $originalFile ?? explode(".", $backupFile)[0]; 
        $originalPath = $this->path . $originalFile . ".sdb";
    
         
        $backupData = file_get_contents($backupPath);
        file_put_contents($originalPath, $backupData);
        
        return true;  
    }    
    public function delete($type, $file, $backupFolder = "backup") {
        if ($type === "main") {
             
            $filePath = $this->path . $file . ".sdb";
        } elseif ($type === "backup") {
            
            $filePath = $this->path . rtrim($backupFolder, "/") . "/" . $file;
        } else {
            return false;  
        }
    
        
        if (file_exists($filePath)) {
            unlink($filePath);
            return true;  
        } else {
            return false;  
        }
    }
    
}


?>
