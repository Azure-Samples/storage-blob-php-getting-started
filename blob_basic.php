<?php
/**----------------------------------------------------------------------------------
* Microsoft Developer & Platform Evangelism
*
* Copyright (c) Microsoft Corporation. All rights reserved.
*
* THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY KIND, 
* EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE IMPLIED WARRANTIES 
* OF MERCHANTABILITY AND/OR FITNESS FOR A PARTICULAR PURPOSE.
*----------------------------------------------------------------------------------
* The example companies, organizations, products, domain names,
* e-mail addresses, logos, people, places, and events depicted
* herein are fictitious.  No association with any real company,
* organization, product, domain name, email address, logo, person,
* places, or events is intended or should be inferred.
*----------------------------------------------------------------------------------
**/

/** -------------------------------------------------------------
# Azure Storage Blob Sample - Demonstrate how to use the Blob Storage service. 
# Blob storage stores unstructured data such as text, binary data, documents or media files. 
# Blobs can be accessed from anywhere in the world via HTTP or HTTPS. 
#
# Documentation References: 
#  - What is a Storage Account - http://azure.microsoft.com/en-us/documentation/articles/storage-whatis-account/ 
#  - Getting Started with Blobs - https://azure.microsoft.com/en-us/documentation/articles/storage-php-how-to-use-blobs/
#  - Blob Service Concepts - http://msdn.microsoft.com/en-us/library/dd179376.aspx 
#  - Blob Service REST API - http://msdn.microsoft.com/en-us/library/dd135733.aspx 
#  - Blob Service PHP API - https://github.com/Azure/azure-sdk-for-php/
#  - Storage Emulator - http://azure.microsoft.com/en-us/documentation/articles/storage-use-emulator/ 
#
**/

namespace MicrosoftAzure\Storage\Samples;
require_once "vendor/autoload.php";
require_once "./config.php";
require_once "./random_string.php";

use Config;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use MicrosoftAzure\Storage\Common\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\PageRange;
use MicrosoftAzure\Storage\Blob\Models\ListPageBlobRangesOptions;
use MicrosoftAzure\Storage\Blob\Models\GetBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\DeleteBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;

class BlobBasicSamples
{
    public function runAllSamples() {
      $connectionString = Config::getConnectionString();
      $blobService = ServicesBuilder::getInstance()->createBlobService($connectionString);

      try {

        $containerName = "blockblobs".generateRandomString();
        # Create a new container
        echo "Create a container with name {$containerName}".PHP_EOL;
        $this->createSampleContainer($blobService, $containerName);
        echo PHP_EOL;

        echo "* Block blob operations *".PHP_EOL;
        $this->blockBlobOperations($blobService, $containerName);
        echo PHP_EOL;

        echo "* Page blob operations *".PHP_EOL;
        $this->pageBlobOperations($blobService, $containerName);
        echo PHP_EOL;

        echo "* Snapshot blob operations *".PHP_EOL;
        $this->snapshotOperations($blobService, $containerName);
        echo PHP_EOL;

        # Delete the container
        echo "Delete Container";
        $blobService->deleteContainer($containerName);
      }
      catch(ServiceException $e) {
        echo "Error occurred in the sample.".$e->getMessage().PHP_EOL;
      }
    }

    # Performs Block Blob operations: 
    #   - Creates a block blob by uploading a file
    #   - List blobs in the container 
    #   - Downloads the blob and saves to a file
    #   - Deletes the blob
    function blockBlobOperations($blobService, $containerName) {
      $fileToUpload = "HelloWorld.png";

      # Upload file as a block blob
      echo "Uploading BlockBlob".PHP_EOL;
        
      $content = fopen($fileToUpload, "r");

      $blobService->createBlockBlob($containerName, $fileToUpload, $content);

      # List all the blobs in the container 
      echo "List Blobs in Container".PHP_EOL;
      $listBlobsResult = $blobService->listBlobs($containerName);

      foreach ($listBlobsResult->getBlobs() as $blob) {
        echo "  blob name ".$blob->getName().PHP_EOL;
      }

      # Download the blob
      echo "Download the blob".PHP_EOL;
      $getBlobResult = $blobService->getBlob($containerName, $fileToUpload);

      file_put_contents("HelloWorldCopy.png", $getBlobResult->getContentStream());

      # Clean up after the sample
      echo "Delete block Blob".PHP_EOL;
      $blobService->deleteBlob($containerName, $fileToUpload);
    }

    # Performs Page Blob Operations:
    #   - Creates a page blob
    #   - Creates pages in the blob
    #   - Lists page blob ranges and download the blob
    #   - Deletes the blob
    function pageBlobOperations($blobService, $containerName){
      $blobName = "HelloAppendBlobWorld.txt";
        
      # Create a page blob
      echo "Create Page Blob with name {$blobName}".PHP_EOL;

      $blobService->createPageBlob($containerName, $blobName, 2560);

      # Create pages in a page blob
      echo "Create pages in a page blob".PHP_EOL;
     
      $blobService->createBlobPages($containerName, $blobName, new PageRange(0, 511), generateRandomString(512));
      $blobService->createBlobPages($containerName, $blobName, new PageRange(512, 1023), generateRandomString(512));

      # List page blob ranges
      $listPageBlobRangesOptions = new ListPageBlobRangesOptions();
      $listPageBlobRangesOptions->setRangeStart(0);
      $listPageBlobRangesOptions->setRangeEnd(1023);

      echo "List Page Blob Ranges".PHP_EOL;
      $listPageBlobRangesResult = $blobService->listPageBlobRanges($containerName, $blobName, $listPageBlobRangesOptions);
      
      foreach ($listPageBlobRangesResult->getPageRanges() as $pageRange) {
        echo "Range:".$pageRange->getStart()."-".$pageRange->getEnd().PHP_EOL;

        $getBlobOptions = new GetBlobOptions();
        $getBlobOptions->setRangeStart($pageRange->getStart());
        $getBlobOptions->setRangeEnd($pageRange->getEnd());

        $getBlobResult = $blobService->getBlob($containerName, $blobName, $getBlobOptions);

        file_put_contents("PageContent.txt", $getBlobResult->getContentStream());
      }

      # Clean up after the sample
    	echo "Delete Blob".PHP_EOL;
      $blobService->deleteBlob($containerName, $blobName);
        
    }

    # Creates, retrieves and deletes a snapshot of a blob
    function snapshotOperations($blobService, $containerName){
      $blobName = "HelloWorld.png";
        
      # Upload file as a block blob
      echo "Uploading BlockBlob".PHP_EOL;
        
      $content = fopen($blobName, "r");

      $blobService->createBlockBlob($containerName, $blobName, $content);

      # Create a snapshot
      echo "Create a Snapshot".PHP_EOL;
      $snapshotResult = $blobService->createBlobSnapshot($containerName, $blobName);

      # Retrieve snapshot
      echo "Retrieve Snapshot".PHP_EOL;

      $getBlobOptions = new GetBlobOptions();
      $getBlobOptions->setSnapshot($snapshotResult->getSnapshot());

      $getBlobResult = $blobService->getBlob($containerName, $blobName, $getBlobOptions);

      file_put_contents("HelloWorldSnapshotCopy.png", $getBlobResult->getContentStream());

      # Clean up after the sample
      echo "Delete Blob and snapshot".PHP_EOL;
      $deleteBlobOptions = new DeleteBlobOptions();
      $deleteBlobOptions->setDeleteSnaphotsOnly(false);

      $blobService->deleteBlob($containerName, $blobName, $deleteBlobOptions);
    }

    # Creates a container
    function createSampleContainer($blobService, $containerName)
    {
        // OPTIONAL: Set public access policy and metadata.
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
    }
}
?>