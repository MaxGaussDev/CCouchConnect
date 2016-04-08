# CCouchConnect

This is a simple PHP CouchDB cURL Wrapper, for basic CRUD commands in CouchDB.

#Importing

To import the class all you need to do is include the CCouchConnect.php file and use the namespace:

```
include('CCouchConnect.php');

use CCouch\Database\CCouchConnect as Database;
```

#Setting up the database

The constructor will support method overload, so basically there are three ways to create a Database Object:
```
$db = new Database('database', 'server');
$db = new Database('database', 'server', 5984);
$db = new Database('database', 'server', 5984, 'username', 'password');
```
By default the port will be set to the usual 5984 on CouchDB. 

#How to use

There are a couple of methods that will allow you to do the basic CRUD for objects. The return values by default will be php's stdObjects.

The Database info:
```
$result = $db->dbInfo();
```

#Creating the document:
```
$document = array(
    "name" => "foo",
    "occupation" => "bar"
);
$result = $db->addNew($document);
```

This will return an std object with createdAt and updatedAt parameters by default (using Datetime), like this:
```
stdClass Object
(
    [_id] => efdf1c6dde49b78bf9834424131037f5
    [_rev] => 1-4fb5af102470b0304292ec418e0cab09
    [name] => foo
    [occupation] => bar
    [createdAt] => stdClass Object
        (
            [date] => 2016-04-08 11:41:06.000000
            [timezone_type] => 3
            [timezone] => Europe/Berlin
        )

    [updatedAt] => stdClass Object
        (
            [date] => 2016-04-08 11:41:06.000000
            [timezone_type] => 3
            [timezone] => Europe/Berlin
        )

)
```
#Getting the document:

There are a couple of methods to retrieve a document from CouchDB:

```

// returns document object by _id
$result = $db->findById('efdf1c6dde49b78bf9834424131037f5'); 

// returns an array of document objects filtered by array of keys and their values
$result = $db->findBy(array("key" => "value")); 

// returns a document object filtered by array of keys and their values
$result = $db->findOneBy(array("key" => "value")); 

// example with our object above:
$result = $db->findOneBy(array("name" => "foo", "occupation" => "bar"));

$result = $db->findAll(); 
// returns an array of all document objects

```
findBy() methods do not create a view in the database, the methods are called over cURL with a temporary view. There will be a view support in the future release.

#Updating and Deleting the document:

Updating a document is simple, you just pass an object as an argument to save() method:

```
// some document from the database
$document = $db->findById('efdf1c6dde49b78bf9834424131037f5');
$document->name = "foo foo";

$result = $db->save($document);
```

Deleting the document:
```
// some document from the database
$document = $db->findById('efdf1c6dde49b78bf9834424131037f5');

$result = $db->delete($document);
```

Although it is not advised, you can also Purge the document. Typical CouchDb purge rules apply:
```
// some document from the database
$document = $db->findById('efdf1c6dde49b78bf9834424131037f5');

$result = $db->purge($document);
```

#Bulk methods:

There are two bulk methods currently. Adding and Deleting multiple documents in a single request:

```
// some documents
$documentsArray = array($document1, $document2, $document3,...); 

// add multiple new documents
$result = $db->saveBulk($documentsArray);

// delete multiple documents
$result = $db->deleteBulk($documentsArray);
```
Both methods will accept array of document objects as argument. Return result will be an array of objects with statuses.


#Additional methods:

```
// get document ids (findAll() withoud document details)
$result = $db->listDocuments();

// get ids of changed documents
$result = $db->listChanges();
```

#Future Releases:

Here is the list of plans for future releases:

- support for offset and limit in find methods with overload
- support for custom views manipulation
- attachments support
