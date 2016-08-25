<?php
/**
 * LICENSE: The MIT License (the "License")
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * https://github.com/azure/azure-storage-php/LICENSE
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @category  Microsoft
 * @package   MicrosoftAzure\Storage\Samples
 * @author    Azure Storage PHP SDK <dmsh@microsoft.com>
 * @copyright 2016 Microsoft Corporation
 * @license   https://github.com/azure/azure-storage-php/LICENSE
 * @link      https://github.com/azure/azure-storage-php
 */
namespace MicrosoftAzure\Storage\Samples;

require_once "vendor/autoload.php";
require_once "./config.php";
require_once "./random_string.php";

use Config;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\DeleteBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;
use MicrosoftAzure\Storage\Blob\Models\CreateBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\ContainerAcl;
use MicrosoftAzure\Storage\Blob\Models\SetBlobPropertiesOptions;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use MicrosoftAzure\Storage\Common\ServiceException;
use MicrosoftAzure\Storage\Common\Models\Logging;
use MicrosoftAzure\Storage\Common\Models\Metrics;
use MicrosoftAzure\Storage\Common\Models\RetentionPolicy;
use MicrosoftAzure\Storage\Common\Models\ServiceProperties;

class BlobAdvancedSamples
{
    public function runAllSamples() {
      $connectionString = Config::getConnectionString();
      $blobService = ServicesBuilder::getInstance()->createBlobService($connectionString);

      try {

        echo PHP_EOL;
        echo "* Container operations *".PHP_EOL;
        $this->containerSample($blobService);

        echo PHP_EOL;
        echo "* Lease operations *".PHP_EOL;
        $this->leaseOperations($blobService);

        echo PHP_EOL;
        echo "* Blob service properties *".PHP_EOL;
        $this->blobServiceProperties($blobService);

        echo PHP_EOL;
        echo "* Container properties *".PHP_EOL;
        $this->containerProperties($blobService);

        echo PHP_EOL;
        echo "* Container metadata *".PHP_EOL;
        $this->containerMetadata($blobService);

        echo PHP_EOL;
        echo "* Container access policy *".PHP_EOL;
        $this->containerAcl($blobService);

        echo PHP_EOL;
        echo "* Blob properties *".PHP_EOL;
        $this->blobProperties($blobService);

        echo PHP_EOL;
        echo "* Blob metadata *".PHP_EOL;
        $this->blobMetadata($blobService);

        echo PHP_EOL;
      }
      catch(ServiceException $e) {
        echo "Error occurred in the sample.".$e->getMessage().PHP_EOL;
      }
    }
    
    function containerSample($blobService) {
        try {
            // Create containers
            $containerName = "mycontainer" . generateRandomString();
            $containerName2 = "mycontainer" . generateRandomString();
            echo "Create container " . $containerName . PHP_EOL;
            $blobService->createContainer($containerName);
            echo "Create container " . $containerName2. PHP_EOL;
            $blobService->createContainer($containerName2);

            echo "List containers" . PHP_EOL;
            $containers = $blobService->listContainers();

            foreach ($containers->getContainers() as $container) {
                echo "Container: " . $container->getName() . PHP_EOL;
            }

            echo "Delete containers" . PHP_EOL;
            $blobService->deleteContainer($containerName);
            $blobService->deleteContainer($containerName2);
            
        } catch(ServiceException $e){
            $code = $e->getCode();
            $error_message = $e->getMessage();
            echo $code.": ".$error_message.PHP_EOL;
        }
    }

    // lease operations
    function leaseOperations($blobService){
        // Create container
        $container = "mycontainer" . generateRandomString();
        echo "Create container " . $container . PHP_EOL;
        $blobService->createContainer($container);

        // Create Blob
        $blob = 'Blob' . generateRandomString();
        echo "Create blob " . $blob . PHP_EOL;
        $contentType = 'text/plain; charset=UTF-8';
        $options = new CreateBlobOptions();
        $options->setContentType($contentType);
        $blobService->createBlockBlob($container, $blob, 'Hello world', $options);
        
        // Acquire lease
        $result = $blobService->acquireLease($container, $blob);

        try {
            echo "Try delete blob without lease" . PHP_EOL;
            $blobService->deleteBlob($container, $blob);

        } catch(ServiceException $e){

            echo "Delete blob with lease" . PHP_EOL;
            $blobOptions = new DeleteBlobOptions();
            $blobOptions->setLeaseId($result->getLeaseId());
            $blobService->deleteBlob($container, $blob, $blobOptions);
        }

        echo "Delete container" . PHP_EOL;
        $blobService->deleteContainer($container);
    }

    // Get and Set Blob Service Properties
    function blobServiceProperties($blobService) {
        // Get blob service properties
        echo "Get Blob Service properties" . PHP_EOL;
        $originalProperties = $blobService->getServiceProperties();

        // Set blob service properties
        echo "Set Blob Service properties" . PHP_EOL;
        $retentionPolicy = new RetentionPolicy();
        $retentionPolicy->setEnabled(true);
        $retentionPolicy->setDays(10);
        
        $logging = new Logging();
        $logging->setRetentionPolicy($retentionPolicy);
        $logging->setVersion('1.0');
        $logging->setDelete(true);
        $logging->setRead(true);
        $logging->setWrite(true);
        
        $metrics = new Metrics();
        $metrics->setRetentionPolicy($retentionPolicy);
        $metrics->setVersion('1.0');
        $metrics->setEnabled(true);
        $metrics->setIncludeAPIs(true);

        $serviceProperties = new ServiceProperties();
        $serviceProperties->setLogging($logging);
        $serviceProperties->setMetrics($metrics);

        $blobService->setServiceProperties($serviceProperties);
        
        // revert back to original properties
        echo "Revert back to original service properties" . PHP_EOL;
        $blobService->setServiceProperties($originalProperties->getValue());

        echo "Service properties sample completed" . PHP_EOL;
    }

    // Get and Set Blob Container Properties
    function containerProperties($blobService){
        $containerName = "mycontainer" . generateRandomString();
        
        echo "Create container " . $containerName . PHP_EOL;
        // Create container options object.
        $createContainerOptions = new CreateContainerOptions();
        // Set public access policy. Possible values are
        // PublicAccessType::CONTAINER_AND_BLOBS and PublicAccessType::BLOBS_ONLY.
        // CONTAINER_AND_BLOBS: full public read access for container and blob data.
        // BLOBS_ONLY: public read access for blobs. Container data not available.
        // If this value is not specified, container data is private to the account owner.
        $createContainerOptions->setPublicAccess(PublicAccessType::CONTAINER_AND_BLOBS);
        // Set container metadata
        $createContainerOptions->addMetaData("key1", "value1");
        $createContainerOptions->addMetaData("key2", "value2");
        // Create container.
        $blobService->createContainer($containerName, $createContainerOptions);

        echo "Get container properties:" . PHP_EOL;
        // Get container properties
        $properties = $blobService->getContainerProperties($containerName);

        echo 'Last modified: ' . $properties->getLastModified()->format('Y-m-d H:i:s') . PHP_EOL;
        echo 'ETAG: ' . $properties->getETag() . PHP_EOL;

        echo "Delete container" . PHP_EOL;
        $blobService->deleteContainer($containerName) . PHP_EOL;
    }

    // Get and Set Blob Container Metadata
    function containerMetadata($blobService){
        $containerName = "mycontainer" . generateRandomString();
        
        echo "Create container " . $containerName . PHP_EOL;
        // Create container options object.
        $createContainerOptions = new CreateContainerOptions();
        // Set container metadata
        $createContainerOptions->addMetaData("key1", "value1");
        $createContainerOptions->addMetaData("key2", "value2");
        // Create container.
        $blobService->createContainer($containerName, $createContainerOptions);

        echo "Get container metadata" . PHP_EOL;
        // Get container properties
        $properties = $blobService->getContainerProperties($containerName);

        foreach ($properties->getMetadata() as $key => $value) {
            echo $key . ": " . $value . PHP_EOL; 
        }

        echo "Delete container" . PHP_EOL;
        $blobService->deleteContainer($containerName);
    }

    // Get and Set Blob Container Acl
    function containerAcl($blobService){
        // Create container
        $container = "mycontainer" . generateRandomString();
        echo "Create container " . $container . PHP_EOL;
        $blobService->createContainer($container);

        // Set container ACL
        $past = new \DateTime("01/01/2010");
        $future = new \DateTime("01/01/2020");
        $acl = new ContainerAcl();
        $acl->setPublicAccess(PublicAccessType::CONTAINER_AND_BLOBS);
        $acl->addSignedIdentifier('123', $past, $future, 'rw');

        $blobService->setContainerACL($container, $acl);

        // Get container ACL
        echo "Get container access policy" . PHP_EOL;
        $acl = $blobService->getContainerACL($container);

        echo 'Public access: ' . $acl->getContainerACL()->getPublicAccess() . PHP_EOL;
        echo 'Signed Identifiers: ' . PHP_EOL;
        echo ' Id: ' . $acl->getContainerACL()->getSignedIdentifiers()[0]->getId() . PHP_EOL;
        echo ' Start: '. $acl->getContainerACL()->getSignedIdentifiers()[0]->getAccessPolicy()->getStart()->format('Y-m-d H:i:s') .PHP_EOL;
        echo ' Expiry: '. $acl->getContainerACL()->getSignedIdentifiers()[0]->getAccessPolicy()->getExpiry()->format('Y-m-d H:i:s') .PHP_EOL;
        echo ' Permission: '. $acl->getContainerACL()->getSignedIdentifiers()[0]->getAccessPolicy()->getPermission() .PHP_EOL;

        echo "Delete container" . PHP_EOL;
        $blobService->deleteContainer($container);
    }

    // Get and Set Blob Properties
    function blobProperties($blobService){
        // Create container
        $container = "mycontainer" . generateRandomString();
        echo "Create container " . $container . PHP_EOL;
        $blobService->createContainer($container);

        // Create blob
        $blob = 'blob' . generateRandomString();
        echo "Create blob " . PHP_EOL;
        $blobService->createPageBlob($container, $blob, 4096);

        // Set blob properties
        echo "Set blob properties" . PHP_EOL;
        $opts = new SetBlobPropertiesOptions();
        $opts->setBlobCacheControl('test');
        $opts->setBlobContentEncoding('UTF-8');
        $opts->setBlobContentLanguage('en-us');
        $opts->setBlobContentLength(512);
        $opts->setBlobContentMD5(null);
        $opts->setBlobContentType('text/plain');
        $opts->setSequenceNumberAction('increment');

        $blobService->setBlobProperties($container, $blob, $opts);

        // Get blob properties
        echo "Get blob properties" . PHP_EOL;
        $result = $blobService->getBlobProperties($container, $blob);
       
        $props = $result->getProperties();

        echo 'Cache control: ' . $props->getCacheControl() . PHP_EOL;
        echo 'Content encoding: ' . $props->getContentEncoding() . PHP_EOL;
        echo 'Content language: ' . $props->getContentLanguage() . PHP_EOL;
        echo 'Content type: ' . $props->getContentType() . PHP_EOL;
        echo 'Content length: ' . $props->getContentLength() . PHP_EOL;
        echo 'Content MD5: ' . $props->getContentMD5() . PHP_EOL;
        echo 'Last modified: ' . $props->getLastModified()->format('Y-m-d H:i:s') . PHP_EOL;
        echo 'Blob type: ' . $props->getBlobType() . PHP_EOL;
        echo 'Lease status: ' . $props->getLeaseStatus() . PHP_EOL;
        echo 'Sequence number: ' . $props->getSequenceNumber() . PHP_EOL;

        echo "Delete blob" . PHP_EOL;
        $blobService->deleteBlob($container, $blob);

        echo "Delete container" . PHP_EOL;
        $blobService->deleteContainer($container);
    }

    // Get and Set Blob Metadata
    function blobMetadata($blobService){
        // Create container
        $container = "mycontainer" . generateRandomString();
        echo "Create container " . $container . PHP_EOL;
        $blobService->createContainer($container);

        // Create blob
        $blob = 'blob' . generateRandomString();
        echo "Create blob " . PHP_EOL;
        $blobService->createPageBlob($container, $blob, 4096);

        // Set blob metadata
        echo "Set blob metadata" . PHP_EOL;
        $metadata = array(
            'key' => 'value',
            'foo' => 'bar',
            'baz' => 'boo');

        $blobService->setBlobMetadata($container, $blob, $metadata);

        // Get blob metadata
        echo "Get blob metadata" . PHP_EOL;
        $result = $blobService->getBlobMetadata($container, $blob);
       
        $retMetadata = $result->getMetadata();

        foreach($retMetadata as $key => $value)  {
            echo $key . ': ' . $value . PHP_EOL;
        }

        echo "Delete blob" . PHP_EOL;
        $blobService->deleteBlob($container, $blob);

        echo "Delete container" . PHP_EOL;
        $blobService->deleteContainer($container);
    }
}
?>