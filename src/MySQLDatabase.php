<?php
namespace TymFrontiers;

class MySQLDatabase{
  private    static    $_db_server;
  private    static    $_db_user;
  private    static    $_db_pass;
  private    static    $_db_name;
  private        			 $_connection;
  private              $_magic_quotes_acitive;
  private              $_real_escape_string_exists;

  public  						 $last_query;
  public 							 $errors = [];
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

  function __construct(string $db_server,string $db_user,string $db_pass,string $db_name=''){
    if( $this->_connection ) $this->closeConnection();
    self::$_db_server = $this->escapeValue($db_server);
    self::$_db_user   = $this->escapeValue($db_user);
    self::$_db_pass   = $this->escapeValue($db_pass);
    self::$_db_name   = $this->escapeValue($db_name);
    $this->openConnection();
    $this->_magic_quotes_acitive = get_magic_quotes_gpc();
    $this->_real_escape_string_exists = function_exists("mysqli_real_escape_string");
  }
  public function dbName(){ return self::$_db_name; }
  // open Database connection using given params
  public function openConnection(){
    $this->_connection = !empty(self::$_db_name) ?
      new \mysqli(self::$_db_server, self::$_db_user, self::$_db_pass, self::$_db_name) :
      new \mysqli(self::$_db_server, self::$_db_user, self::$_db_pass);

    if (mysqli_connect_error()) {
      // error everyone can see
      $this->errors['openConnection'][] =[0,256,"Failed to connect to database.",__FILE__,__LINE__];
      // error for admin only
      $err_arr = error_get_last();
      $this->errors['openConnection'][] = [3,$err_arr['type'],$err_arr["message"],$err_arr['file'],$err_arr['line']];
      return false;
    }else{
      if( isset($this->errors['openConnection']) ) { unset($this->errors['openConnection']); }
      return true;
    }
  }
  public function closeConnection(){
    if(isset($this->_connection)){
      $this->_connection->close();
      unset($this->_connection);
    }
  }
  public function query(string $sql){
    $this->last_query = $sql;
		$result = $this->_connection->query($sql);
		return $this->confirmQuery($result) ? $result : false;
  }
  public function getDBname(){ return self::$_db_name; }
	public function changeDB(string $db_name){
		if( $db_name && $db_name !== self::$_db_name ){
			if( !$this->_connection->select_db($db_name) ){
        $err_arr = error_get_last();
        if($err_arr)  $this->errors['changeDB'][] = [3,$err_arr['type'],$err_arr["message"],$err_arr['file'],$err_arr['line']];
        return false;
      }else{ return true; }
		}
    return false;
	}
  public function fetchArray($result_set){ return $result_set->fetch_array(); }
	public function fetchAssocArray($result_set){ return $result_set->fetch_assoc(); }
	public function fetchAll($result_set){ return $result_set->fetch_all(); }

  public function escapeValue($value){
    if($this->_real_escape_string_exists){// PHP 4.3.0 or higher
			// undo any magic quote effects so mysql_real_escape_string can do the work
			if($this->_magic_quotes_acitive){ $value = stripcslashes($value);}
			$value = mysqli_real_escape_string($this->_connection,$value);
		} else{ // before PHP 4.3.0
			// if magic quotes aren't already on then add slashes manually
			if( !$this->_magic_quotes_acitive ){ $value = addcslashes($value,"\0..\37!@\177..\377");}
			// if magic quotes are active then slashes already exist
		}
		return $value;
  }

    // Database-neutral methods
    public function confirmQuery($result){
			if(!$result){
				// error everyone can see
				$this->errors['query'][] =[0,256,"Database query failed.",__FILE__,__LINE__];
				// error for admin only
				$this->errors['query'][] =[4,256,"Error: {$this->_connection->error}",__FILE__,__LINE__];
				$this->errors['query'][] =[4,256,"Last query: {$this->last_query} ",__FILE__,__LINE__];
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
}
