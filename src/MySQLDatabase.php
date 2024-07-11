<?php
namespace TymFrontiers;

class MySQLDatabase{
  private  $_db_server;
  private  $_db_server_port = "3306";
  private  $_db_user;
  private  $_db_pass;
  private  $_db_name;
  private  $_connection;

  private  $_last_query;
  public 	 $errors = [];
  # How error is pushed to this array..
  # key => [ // key can be method name/instannce where error occured
  #   [
  #     int view_rank, // who can see this error. 0 = anyone, 1 >= author, 2 >= editor, 3 >= manager, 4 >= admin, 5 super-admin
  #     int error_type, // php error type
  #     string error_message, // Error itself
  #     string file, // where error ocured. e.g __FILE__
  #     string line // where error ocured. e.g __LINE__
  #   ]
  # ]

  function __construct(string $db_server,string $db_user,string $db_pass,string $db_name='', bool $new_conn = false, string|null $port = "3306"){
    if ( !$new_conn && $this->_connection ) $this->closeConnection();
    $this->_db_server = $this->escapeValue($db_server);
    $this->_db_user   = $this->escapeValue($db_user);
    $this->_db_pass   = $this->escapeValue($db_pass);
    $this->_db_name   = $this->escapeValue($db_name);
    if (!empty($port)) $this->_db_server_port = $this->escapeValue($port);
    $this->openConnection();
  }
  public function dbName() { return $this->_db_name; } // deprecated
  // open Database connection using given params
  public function openConnection(){
    try {
      $this->_connection = !empty($this->_db_name) ?
        new \mysqli($this->_db_server, $this->_db_user, $this->_db_pass, $this->_db_name, $this->_db_server_port) :
        new \mysqli($this->_db_server, $this->_db_user, $this->_db_pass, null, $this->_db_server_port);
      if (\mysqli_connect_error()) {
        // error everyone can see
        $this->errors['openConnection'][] =[0,256,"Failed to connect to database.",__FILE__,__LINE__];
        // error for admin only
        $err_arr = \error_get_last();
        $this->errors['openConnection'][] = [7,$err_arr['type'],$err_arr["message"],$err_arr['file'],$err_arr['line']];
        return false;
      }else{
        if( isset($this->errors['openConnection']) ) { unset($this->errors['openConnection']); }
        return true;
      }
    } catch (\Throwable $th) {
      throw new \Exception("Db Connection Error: {$th->getMessage()}", 1);      
    }

  }
  public function closeConnection(){
    if(isset($this->_connection)){
      $this->_connection->close();
      unset($this->_connection);
    }
  }
  public function query(string $sql){
    $this->_last_query = $sql;
    $result = false;
    try {
      $result = $this->_connection->query($sql);
    } catch (\Throwable $th) {
      $this->errors['query'][] = [7, 256, $th->getMessage(), __FILE__, __LINE__];
    }
		return ($result && $this->confirmQuery($result)) ? $result : false;
  }
  public function multiQuery(string $sql){
    $this->_last_query = $sql;
    try {
      $result = $this->_connection->multi_query($sql);
      if ($result) {
        return true;
      }
    } catch (\Throwable $th) {
      //throw $th;
      $this->errors["multiQuery"][] = [0,256, "Multi-Query Erroe: {$th->getMessage()}",__FILE__,__LINE__];
    }
    $this->errors["multiQuery"][] = [0,256, "Multi-Query failed!",__FILE__,__LINE__];
    if ($this->_connection->errno) $this->errors["multiQuery"][] = [7,256, $this->_connection->error,__FILE__,__LINE__];
		return false;
  }
  public function useResult () { return $this->_connection->use_result; }
  public function nextResult () { return $this->_connection->next_result; }
  public function moreResults () { return $this->_connection->more_results; }
	public function changeDB(string $db_name){
		if( $db_name && $db_name !== $this->_db_name ){
			if( !$this->_connection->select_db($db_name) ){
        $err_arr = \error_get_last();
        if($err_arr)  $this->errors['changeDB'][] = [7,$err_arr['type'],$err_arr["message"],$err_arr['file'],$err_arr['line']];
        return false;
      }else{ 
        $this->_db_name = $db_name;
        return true; 
      }
		}
    return false;
	}
  public function fetchArray($result_set){ return $result_set->fetch_array(); }
	public function fetchAssocArray($result_set){ return $result_set->fetch_assoc(); }
	public function fetchAll($result_set){ return $result_set->fetch_all(); }

  public function escapeValue(string $value){
    $value = $this->_connection
      ? \mysqli_real_escape_string($this->_connection,$value)
      : \addslashes($value);
		return $value;
  }

  // Database-neutral methods
  public function confirmQuery($result){
    if(!$result){
      // error everyone can see
      $this->errors['query'][] =[0,256,"Database query failed.",__FILE__,__LINE__];
      // error for admin only
      $this->errors['query'][] =[7,256,"Error: {$this->_connection->error}",__FILE__,__LINE__];
      $this->errors['query'][] =[7,256,"Last query: {$this->_last_query} ",__FILE__,__LINE__];
      return false;
    }else{
      if( isset($this->errors['query']) ){ unset($this->errors['query']); }
      return true;
    }
  }
  public function numRows($result_set){
    if( $result_set ){
      try {
        $row = $result_set->num_rows;
        return $row;
      } catch (\Exception $e) {
        $this->errors["numRows"][] = [4,256,$e->getMessage(),__FILE__,__LINE__];
      }
    }

    return false;
  }
  public function insertId(){ return $this->_connection->insert_id; }
  public function affectedRows(){ return $this->_connection->affected_rows; }
  public function getDatabase() { return $this->_db_name; }
  public function getServer() { return $this->_db_server; }
  public function getUser() { return $this->_db_user; }
  public function lastQuery () { return $this->_last_query; }
  public function checkConnection ():bool {
    return $this->_connection && $this->_connection instanceof \mysqli;
  }
}
