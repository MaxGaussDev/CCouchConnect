<?php
/**
 * \brief   
 * \details     
 * @author  Mario Pastuović
 * @version 0.5.2
 * \date 14.04.16.
 * \copyright
 *     This code and information is provided "as is" without warranty of
 *     any kind, either expressed or implied, including but not limited to
 *     the implied warranties of merchantability and/or fitness for a
 *     particular purpose.
 *     \par
 *     Copyright (c) Poslovanje 2 d.o.o. All rights reserved
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
        $url = rtrim($this->base_curl, '/');
        return $this->runCURLRequestWithData('GET', $url, null);
    }

    public function listDocuments(){
        $url = $this->base_curl.'_all_docs';
        return $this->runCURLRequestWithData('GET', $url, null)->rows;
    }

    public function listChanges(){
        $url = $this->base_curl.'_changes';
        return $this->runCURLRequestWithData('GET', $url, null);
    }

    public function findById($id){
        $url = $this->base_curl.''.$id;
        return $this->runCURLRequestWithData('GET', $url, null);
    }

    public function findAll(){
        $url = $this->base_curl.'_all_docs?include_docs=true';
        $result = $this->runCURLRequestWithData('GET', $url, null)->rows;
        $docs_only = array();
        foreach ($result as $r_doc){
            array_push($docs_only, $r_doc->doc);
        }
        return $docs_only;
    }

    public function findByNoCache($conditions){

        $view_cmd = $this->createViewCodeFromSearchConditionsArray($conditions);
        $cmd_json = array("map" => $view_cmd);

        $url = $this->base_curl.'_temp_view';
        $result = $this->runCURLRequestWithData('POST', $url, json_encode($cmd_json));

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

        $url = $this->base_curl.'_design/'.$this->base_design_document.'/_view/'.$cmd_json_key;
        $result = $this->runCURLRequestWithData('GET', $url, null);
        if(!$result->error){
            return $result;
        }else{
            $view_cmd = $this->createViewCodeFromSearchConditionsArray($conditions);
            if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                return $this->runCURLRequestWithData('GET', $url, null);
            }else{
                if(!$this->checkForLocalDesignDocument()){
                    if($this->createLocalDesignDocument()){
                        if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                            return $this->runCURLRequestWithData('GET', $url, null);
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }else{
                    if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                        return $this->runCURLRequestWithData('GET', $url, null);
                    }else{
                        return false;
                    }
                }
            }
        }
    }

    public function findOneByNoCache($conditions){

        $view_cmd = $this->createViewCodeFromSearchConditionsArray($conditions);
        $cmd_json = array("map" => $view_cmd);
        $url = $this->base_curl.'_temp_view?limit=1';
        return $this->runCURLRequestWithData('POST', $url, json_encode($cmd_json))->rows[0];
    }

    public function findOneBy($conditions){

        // create md5 for view key
        $cmd_json_key = md5(json_encode($conditions));

        $url = $this->base_curl.'_design/'.$this->base_design_document.'/_view/'.$cmd_json_key.'?limit=1';
        $result = $this->runCURLRequestWithData('GET', $url, null)->rows[0]->value;

        if(!$result->error){
            return $result;
        }else{
            $view_cmd = $this->createViewCodeFromSearchConditionsArray($conditions);
            if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                return $this->runCURLRequestWithData('GET', $url, null);
            }else{
                if(!$this->checkForLocalDesignDocument()){
                    if($this->createLocalDesignDocument()){
                        if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                            return $this->runCURLRequestWithData('GET', $url, null)->rows[0]->value;
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }else{
                    if($this->addViewToLocalDesignDocument($cmd_json_key, $view_cmd)){
                        return $this->runCURLRequestWithData('GET', $url, null)->rows[0]->value;
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

        $url = rtrim($this->base_curl, '/');
        return $this->runCURLRequestWithData('POST', $url, json_encode($document));
    }

    public function save($document){
        $document->updatedAt  = new DateTime("now");
        $url = $this->base_curl.''.$document->_id;
        return $this->runCURLRequestWithData('PUT', $url, json_encode($document));
    }

    public function delete($document){
        $document->_deleted  = true;
        $url = rtrim($this->base_curl, '/');
        return $this->runCURLRequestWithData('POST', $url, json_encode($document));
    }

    public function purge($document){
        $doc_id = $document->_id;
        $doc_rev = $document->_rev;
        $purge_info = array($doc_id => array($doc_rev));
        $url = $this->base_curl.'_purge';
        return $this->runCURLRequestWithData('POST', $url, json_encode($purge_info));
    }

    public function search($keyword){

        $curl = curl_init();
        curl_setopt($curl,CURLOPT_URL,$this->base_curl.'_design/'.$this->base_design_document.'/_view/search_all?key=%22'.$keyword.'%22&include_docs=true');
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        $result = json_decode(curl_exec($curl));
        curl_close($curl);

        $docs = array();
        foreach($result->rows as $row){
            array_push($docs, $row->doc);
        }
        return $docs;
    }

    #region BULK METHODS

    public function saveBulk($documents){
        $docs_list = array();
        foreach ($documents as $doc){
            if(!$doc->createdAt){$doc["createdAt"] = new DateTime("now");}
            if(!$doc->updatedAt){$doc["updatedAt"] = new DateTime("now");}else{$doc->updatedAt = new DateTime("now");}
            array_push($docs_list, $doc);
        }
        $url = $this->base_curl.'_bulk_docs';
        return $this->runCURLRequestWithData('POST', $url, json_encode(array("docs" => $docs_list)));
    }

    public function deleteBulk($documents){
        $docs_list = array();
        foreach ($documents as $doc){
            $doc->_deleted  = true;
            array_push($docs_list, $doc);
        }
        return $this->saveBulk($docs_list);
    }

    #endregion

    #region INTERNAL METHODS

    protected function checkForLocalDesignDocument(){
        $url = $this->base_curl.'_design/'.$this->base_design_document;
        if(!$this->runCURLRequestWithData('GET', $url, null)->error){
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
        $url = $this->base_curl.'_design/'.$this->base_design_document;
        $doc_views = $this->runCURLRequestWithData('GET', $url, null);
        if($doc_views->views->$view_name){
            return true;
        }else{
            return false;
        }
    }

    protected function addViewToLocalDesignDocument($view_name, $code){
            $vdoc = $this->findById('_design/'.$this->base_design_document);
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

    protected function createViewCodeFromSearchConditionsArray($conditions){
        // create javascript function for view
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
        return  $view_cmd_head.''.$view_cmd_cond.''.$view_cmd_end;
    }

    protected function runCURLRequestWithData($method = 'GET', $url, $data = null){

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        if($data != null){
                curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }

        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        $response = json_decode($result);
        curl_close($curl);
        return $response;
    }

    #endregion


}
