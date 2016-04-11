<?php
/**
 * \brief   
 * \details     
 * @author  Mario Pastuović
 * @version 1.0
 * \date 05.04.16.
 * \copyright
 *     This code and information is provided "as is" without warranty of
 *     any kind, either expressed or implied, including but not limited to
 *     the implied warranties of merchantability and/or fitness for a
 *     particular purpose.
 *     \par
 *     Copyright (c) Poslovanje 2 d.o.o. All rights reserved
 * Created by PhpStorm.
 */

namespace CCouch\Database;

use DateTime;

class CCouchConnect {

    protected $database;
    protected $server;
    protected $port;
    protected $username;
    protected $password;
    protected $base_curl;
    protected $base_design_document;

    #region CLASS CONSTRUCTS

    public function __construct() {
        $get_arguments       = func_get_args();
        $number_of_arguments = func_num_args();

        if (method_exists($this, $method_name = '__construct'.$number_of_arguments)) {
            call_user_func_array(array($this, $method_name), $get_arguments);
        }
    }

    public function __construct2($argument1, $argument2) {
        $this->database = $argument1;
        $this->server = $argument2;
        $this->port = 5984;
        $this->base_curl = 'http://'.$argument2.':5984/'.$argument1.'/';
        $this->base_design_document = 'ccouch_views';
    }

    public function __construct3($argument1, $argument2, $argument3 = 5984) {
        $this->database = $argument1;
        $this->server = $argument2;
        $this->port = $argument3;
        $this->base_curl = 'http://'.$argument2.':'.$argument3.'/'.$argument1.'/';
        $this->base_design_document = 'ccouch_views';
    }

    public function __construct5($argument1, $argument2, $argument3 = 5984, $argument4, $argument5) {
        $this->database = $argument1;
        $this->server = $argument2;
        $this->port = $argument3;
        $this->username = $argument4;
        $this->password = $argument5;
        $this->base_curl = 'http://'.$argument4.':'.$argument5.'@'.$argument2.':'.$argument3.'/'.$argument1.'/';
        $this->base_design_document = 'ccouch_views';
    }

    #endregion

    #region GETTERS AND SETTERS

    public function getDatabase()
    {
        return $this->database;
    }

    public function setDatabase($database)
    {
        $this->database = $database;
    }

    public function getServer()
    {
        return $this->server;
    }

    public function setServer($server)
    {
        $this->server = $server;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function setPort($port)
    {
        $this->port = $port;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    #endregion

    public function dbInfo(){
        $cmd = 'curl -X GET '.rtrim($this->base_curl, '/');
        return json_decode(shell_exec($cmd));
    }

    public function listDocuments(){
        $cmd = 'curl -X GET '.$this->base_curl.'_all_docs';
        return json_decode(shell_exec($cmd))->rows;
    }

    public function listChanges(){
        $cmd = 'curl -X GET '.$this->base_curl.'_changes';
        return json_decode(shell_exec($cmd))->results;
    }

    public function findById($id){
        $cmd = 'curl -X GET '.$this->base_curl.''.$id;
        return json_decode(shell_exec($cmd));
    }

    public function findAll(){
        $cmd = 'curl -X GET '.$this->base_curl.'_all_docs?include_docs=true';
        $result = json_decode(shell_exec($cmd))->rows;
        $docs_only = array();
        foreach ($result as $r_doc){
            array_push($docs_only, $r_doc->doc);
        }
        return $docs_only;
    }

    public function findByNoCache($conditions){

        $keys = array_keys($conditions);
        $values = array_values($conditions);

        $view_cmd_head = 'function(doc) {if(';
        $view_cmd_end = '){emit(doc.id, doc);}}';
        $view_cmd_cond = '';

        if(count($conditions) > 1){
            for ($index = 0; $index <= count($conditions)-1; $index++){
                if($index == count($conditions)-1){
                    $view_cmd_cond .= 'doc.'.$keys[$index].' == "'.$values[$index].'"';
                }else{
                    $view_cmd_cond .= 'doc.'.$keys[$index].' == "'.$values[$index].'" && ';
                }
            }
        }else{
           $view_cmd_cond .= 'doc.'.$keys[0].' == "'.$values[0].'"';
        }
        $view_cmd = $view_cmd_head.''.$view_cmd_cond.''.$view_cmd_end;
        $cmd_json = array("map" => $view_cmd);
        $cmd = 'curl -H \'Content-Type: application/json\' -X POST '.$this->base_curl.'_temp_view -d \''.json_encode($cmd_json).'\'';

        $result = json_decode(shell_exec($cmd));
        $docs = array();
        foreach ($result->rows as $row){
            $doc_data = $row->value;
            array_push($docs, $doc_data);
        }
        return array("total" => $result->total_rows, "documents" => $docs);
    }

    public function findBy($conditions){

        // create md5 for view key
        $cmd_json_key = md5(json_encode($conditions));

        // check for view results if not existent then check and create view
        $cmd = 'curl -X GET '.$this->base_curl.'_design/'.$this->base_design_document.'/_view/'.$cmd_json_key;
        $result = json_decode(shell_exec($cmd));
        if(!$result->error){
            return $result;
        }else{
            // create function for view
            $keys = array_keys($conditions);
            $values = array_values($conditions);

            $view_cmd_head = 'function(doc) {if(';
            $view_cmd_end = '){emit(doc.id, doc);}}';
            $view_cmd_cond = '';

            if(count($conditions) > 1){
                for ($index = 0; $index <= count($conditions)-1; $index++){
                    if($index == count($conditions)-1){
                        $view_cmd_cond .= 'doc.'.$keys[$index].' == "'.$values[$index].'"';
                    }else{
                        $view_cmd_cond .= 'doc.'.$keys[$index].' == "'.$values[$index].'" && ';
                    }
                }
            }else{
                $view_cmd_cond .= 'doc.'.$keys[0].' == "'.$values[0].'"';
            }
            $view_cmd = $view_cmd_head.''.$view_cmd_cond.''.$view_cmd_end;
            if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                return json_decode(shell_exec($cmd));
            }else{
                if(!$this->checkForLocalDesignDocument()){
                    if($this->createLocalDesignDocument()){
                        if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                            return json_decode(shell_exec($cmd));
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }else{
                    if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                        return json_decode(shell_exec($cmd));
                    }else{
                        return false;
                    }
                }
            }
        }
    }

    public function findOneByNoCache($conditions){

        $keys = array_keys($conditions);
        $values = array_values($conditions);

        $view_cmd_head = 'function(doc) {if(';
        $view_cmd_end = '){emit(doc.id, doc);}}';
        $view_cmd_cond = '';
        if(count($conditions) > 1){
            for ($index = 0; $index <= count($conditions)-1; $index++){
                if($index == count($conditions)-1){
                    $view_cmd_cond .= 'doc.'.$keys[$index].' == "'.$values[$index].'"';
                }else{
                    $view_cmd_cond .= 'doc.'.$keys[$index].' == "'.$values[$index].'" && ';
                }
            }
        }else{
            $view_cmd_cond .= 'doc.'.$keys[0].' == "'.$values[0].'"';
        }
        $view_cmd = $view_cmd_head.''.$view_cmd_cond.''.$view_cmd_end;
        $cmd_json = array("map" => $view_cmd);
        $cmd = 'curl -H \'Content-Type: application/json\' -X POST '.$this->base_curl.'_temp_view?limit=1 -d \''.json_encode($cmd_json).'\'';

        return json_decode(shell_exec($cmd))->rows[0]->value;
    }

    public function findOneBy($conditions){

        // create md5 for view key
        $cmd_json_key = md5(json_encode($conditions));

        // check for view results if not existent then check and create view
        $cmd = 'curl -X GET '.$this->base_curl.'_design/'.$this->base_design_document.'/_view/'.$cmd_json_key.'?limit=1';
        $result = json_decode(shell_exec($cmd))->rows[0]->value;

        if(!$result->error){
            return $result;
        }else{
            // create function for view
            $keys = array_keys($conditions);
            $values = array_values($conditions);

            $view_cmd_head = 'function(doc) {if(';
            $view_cmd_end = '){emit(doc.id, doc);}}';
            $view_cmd_cond = '';

            if(count($conditions) > 1){
                for ($index = 0; $index <= count($conditions)-1; $index++){
                    if($index == count($conditions)-1){
                        $view_cmd_cond .= 'doc.'.$keys[$index].' == "'.$values[$index].'"';
                    }else{
                        $view_cmd_cond .= 'doc.'.$keys[$index].' == "'.$values[$index].'" && ';
                    }
                }
            }else{
                $view_cmd_cond .= 'doc.'.$keys[0].' == "'.$values[0].'"';
            }
            $view_cmd = $view_cmd_head.''.$view_cmd_cond.''.$view_cmd_end;
            if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                return json_decode(shell_exec($cmd));
            }else{
                if(!$this->checkForLocalDesignDocument()){
                    if($this->createLocalDesignDocument()){
                        if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                            return json_decode(shell_exec($cmd))->rows[0]->value;
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }else{
                    if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                        return json_decode(shell_exec($cmd))->rows[0]->value;
                    }else{
                        return false;
                    }
                }
            }
        }
    }

    public function addNew($document){
        $document['createdAt']  = new DateTime("now");
        $document['updatedAt']  = new DateTime("now");
        $cmd = 'curl -H \'Content-Type: application/json\' -X POST '.rtrim($this->base_curl, '/').' -d \''.json_encode($document).'\'';
        return json_decode(shell_exec($cmd));
    }

    public function save($document){
        $document->updatedAt  = new DateTime("now");
        $cmd = 'curl -H \'Content-Type: application/json\' -X PUT '.$this->base_curl.''.$document->_id.' -d \''.json_encode($document).'\'';
        return json_decode(shell_exec($cmd));
    }

    public function delete($document){
        $document->_deleted  = true;
        $cmd = 'curl -H \'Content-Type: application/json\' -X POST '.rtrim($this->base_curl, '/').' -d \''.json_encode($document).'\'';
        return json_decode(shell_exec($cmd));
    }

    public function purge($document){
        $doc_id = $document->_id;
        $doc_rev = $document->_rev;
        $purge_info = array($doc_id => array($doc_rev));
        $cmd = 'curl -H \'Content-Type: application/json\' -X POST '.$this->base_curl.'_purge -d \''.json_encode($purge_info).'\'';
        return json_decode(shell_exec($cmd));
    }

    #region BULK METHODS

    public function saveBulk($documents){
        $docs_list = array();
        foreach ($documents as $doc){
            if(!$doc->createdAt){$doc["createdAt"] = new DateTime("now");}
            if(!$doc->updatedAt){$doc["updatedAt"] = new DateTime("now");}else{$doc->updatedAt = new DateTime("now");}
            array_push($docs_list, $doc);
        }
        $cmd = 'curl -H \'Content-Type: application/json\' -X POST '.$this->base_curl.'_bulk_docs -d \''.json_encode(array("docs" => $docs_list)).'\'';
        return json_decode(shell_exec($cmd));
    }

    public function deleteBulk($documents){
        $docs_list = array();
        foreach ($documents as $doc){
            $doc->_deleted  = true;
            array_push($docs_list, $doc);
        }
        $cmd = 'curl -H \'Content-Type: application/json\' -X POST '.$this->base_curl.'_bulk_docs -d \''.json_encode(array("docs" => $docs_list)).'\'';
        return json_decode(shell_exec($cmd));
    }

    #endregion

    #region INTERNAL METHODS

    protected function checkForLocalDesignDocument(){
        $cmd = 'curl -X GET '.$this->base_curl.'_design/'.$this->base_design_document;
        if(!json_decode(shell_exec($cmd))->error){
            return true;
        }else{
            return false;
        }
    }

    protected function createLocalDesignDocument(){
            $desing_doc = array(
                "_id" => "_design/{$this->base_design_document}",
                "views" => array(
                    "search_all" => array(
                        "map" => "function(doc) { Object.keys(doc).forEach(function(key) { if(doc[key]) emit(doc[key], null);  }) };"
                    )
                )
            );
            if(!$this->addNew($desing_doc)->error){
                return true;
            }else{
                return false;
            }
    }

    protected function checkForViewInLocalDesignDocument($view_name){
        $cmd = 'curl -X GET '.$this->base_curl.'_design/'.$this->base_design_document;
        $doc_views = json_decode(shell_exec($cmd));
        if($doc_views->views->$view_name){
            return true;
        }else{
            return false;
        }
    }

    protected function addViewToLocalDesignDocument($view_name, $code){
            $vdoc = $this->findById('_design/'.$this->base_design_document);
            // create new custom view and add to the design document
            $vdoc->views->$view_name = array(
                "map" => $code
            );
            $vdoc->updatedAt = new DateTime('now');
            if($this->save($vdoc)->error){
                return false;
            }else{
                return true;
            }
    }

    #endregion

}
