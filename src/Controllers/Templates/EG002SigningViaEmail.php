<?php
/**
 * Example 002: Remote signer, cc, envelope has three documents
 */

namespace Example\Controllers\Templates;

use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\CarbonCopy;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\Tabs;
use Example\Controllers\BaseController;
use Example\Services\ClientService;
use Example\Services\RouterService;

class EG002SigningViaEmail extends BaseController
{
    /** ClientService */
    private $clientService;

    /** RouterService */
    private $routerService;

    /** Specific template arguments */
    private $args;

    private $eg = "eg002";  # reference (and url) for this example

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->args = $this->getTemplateArgs();

// Check if $uploadOk is set to 0 by an error

            if ($this->args['uploadOk'] == 0) {
                echo "Sorry, your file was not uploaded.";
// if everything is ok, try to upload file
            } else {
                if (move_uploaded_file($_FILES["upload"]["tmp_name"], $this->args['target_file'])) {
                    echo "The file " . basename($_FILES["upload"]["name"]) . " has been uploaded.";
                } else {
                    echo "Sorry, there was an error uploading your file.";
                }
            }

        $this->clientService = new ClientService($this->args);
        $this->routerService = new RouterService();
        parent::controller($this->eg, $this->routerService, basename(__FILE__));
    }

    /**
     * 1. Check the token
     * 2. Call the worker method
     * 3. Redirect the user to the signing ceremony
     *
     * @return void
     * @throws ApiException for API problems and perhaps file access \Exception too.
     */
    public function createController(): void
    {
        $minimum_buffer_min = 3;
        if ($this->routerService->ds_token_ok($minimum_buffer_min)) {
            # 2. Call the worker method
            # More data validation would be a good idea here
            # Strip anything other than characters listed
            $results = $this->worker($this->args);

            if ($results) {
                $_SESSION["envelope_id"] = $results["envelope_id"]; # Save for use by other examples
                # which need an envelope_id
                $this->clientService->showDoneTemplate(
                    "Envelope sent",
                    "Envelope sent",
                    "The envelope has been created and sent!<br/> Envelope ID {$results["envelope_id"]}."
                );
            }
        } else {
            $this->clientService->needToReAuth($this->eg);
        }
    }


    /**
     * Do the work of the example
     * 1. Create the envelope request object
     * 2. Send the envelope
     *
     * @param  $args array
     * @return array ['redirect_url']
     * @throws ApiException for API problems and perhaps file access \Exception too.
     */
    # ***DS.snippet.0.start
    public function worker($args): array
    {
        # 1. Create the envelope request object
        $envelope_definition = $this->make_envelope($args["envelope_args"]);
        $envelope_api = $this->clientService->getEnvelopeApi();

        # 2. call Envelopes::create API method
        # Exceptions will be caught by the calling function
        try {
            $results = $envelope_api->createEnvelope($args['account_id'], $envelope_definition);
        } catch (ApiException $e) {
            $this->clientService->showErrorTemplate($e);
            exit;
        }

        return ['envelope_id' => $results->getEnvelopeId()];
    }

    /**
     * Creates envelope definition
     * Document 1: An HTML document.
     * Document 2: A Word .docx document.
     * Document 3: A PDF document.
     * DocuSign will convert all of the documents to the PDF format.
     * The recipients' field tags are placed using <b>anchor</b> strings.
     *
     * Parameters for the envelope: signer_email, signer_name, signer_client_id
     *
     * @param  $args array
     * @return EnvelopeDefinition -- returns an envelope definition
     */
    private function make_envelope(array $args): EnvelopeDefinition
    {
        # document 1 (html) has sign here anchor tag **signature_1**
        # document 2 (docx) has sign here anchor tag /sn1/
        # document 3 (pdf)  has sign here anchor tag /sn1/
        #
        # The envelope has two recipients.
        # recipient 1 - signer
        # recipient 2 - cc
        # The envelope will be sent first to the signer.
        # After it is signed, a copy is sent to the cc person.
        #
        # create the envelope definition
        $envelope_definition = new EnvelopeDefinition([
            'email_subject' => 'Please sign this document set'
        ]);
        $content_bytes = file_get_contents($args['target_file']);
        $doc1_b64 = base64_encode($content_bytes);
        /* # read files 2 and 3 from a local directory
         # The reads could raise an exception if the file is not available!
         $content_bytes = file_get_contents($args['target_file']);
         $doc2_b64 = base64_encode($content_bytes);
         $content_bytes = file_get_contents(self::DEMO_DOCS_PATH . $GLOBALS['DS_CONFIG']['doc_pdf']);
         $doc3_b64 = base64_encode($content_bytes);*/

        # Create the document models
        $document1 = new Document([  # create the DocuSign document object
            'document_base64' => $doc1_b64,
            'name' => 'Anmeldung',  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
        ]);
        /*    $document2 = new Document([  # create the DocuSign document object
                'document_base64' => $doc2_b64,
                'name' => 'Battle Plan',  # can be different from actual file name
                'file_extension' => 'docx',  # many different document types are accepted
                'document_id' => '2'  # a label used to reference the doc
            ]);
            $document3 = new Document([  # create the DocuSign document object
                'document_base64' => $doc3_b64,
                'name' => 'Lorem Ipsum',  # can be different from actual file name
                'file_extension' => 'pdf',  # many different document types are accepted
                'document_id' => '3'  # a label used to reference the doc
            ]);*/
        # The order in the docs array determines the order in the envelope
        $envelope_definition->setDocuments([$document1]);


        # Create the signer recipient model
        $signer1 = new Signer([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'recipient_id' => "1", 'routing_order' => "1"]);
        $signer2 = new Signer([
            'email' => $args['signer2_email'], 'name' => $args['signer2_name'],
            'recipient_id' => "2", 'routing_order' => "2"]);
        $signer3 = new Signer([
            'email' => $args['signer3_email'], 'name' => $args['signer3_name'],
            'recipient_id' => "3", 'routing_order' => "3"]);

        $signer4 = new Signer([
            'email' => $args['signer4_email'], 'name' => $args['signer4_name'],
            'recipient_id' => "4", 'routing_order' => "4"]);

        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.

        # create a cc recipient to receive a copy of the documents
        /*   $cc1 = new CarbonCopy([
               'email' => $args['cc_email'], 'name' => $args['cc_name'],
               'recipient_id' => "4", 'routing_order' => "4"]);*/

        # Create signHere fields (also known as tabs) on the documents,
        # We're using anchor (autoPlace) positioning
        #
        # The DocuSign platform searches throughout your envelope's
        # documents for matching anchor strings. So the
        # signHere2 tab will be used in both document 2 and 3 since they
        #  use the same anchor string for their "signer 1" tabs.
        /*   $sign_here1 = new SignHere([
               'anchor_string' => '**signature_1**', 'anchor_units' => 'pixels',
               'anchor_y_offset' => '10', 'anchor_x_offset' => '20']);
           $sign_here2 = new SignHere([
               'anchor_string' => '/sn1/', 'anchor_units' =>  'pixels',
               'anchor_y_offset' => '10', 'anchor_x_offset' => '20']);*/

        # Add the tabs model (including the sign_here tabs) to the signer
        # The Tabs object wants arrays of the different field/tab types
        /* $signer1->setTabs(new Tabs([
             'sign_here_tabs' => [$sign_here1, $sign_here2]]));*/

        # Add the recipients to the envelope object
        $recipients = new Recipients([
            'signers' => [$signer1, $signer2, $signer3, $signer4]]);
        $envelope_definition->setRecipients($recipients);

        # Request that the envelope be sent by setting |status| to "sent".
        # To request that the envelope be created as a draft, set to "created"
        $envelope_definition->setStatus($args["status"]);

        return $envelope_definition;
    }
    # ***DS.snippet.0.end

    /**
     * Get specific template arguments
     *
     * @return array
     */
    private function getTemplateArgs(): array
    {
        $signer_name = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer_name']);
        $signer_email = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer_email']);
        $signer2_name = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer2_name']);
        $signer2_email = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer2_email']);
        $signer3_name = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer3_name']);
        $signer3_email = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer3_email']);
        $signer4_name = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer4_name']);
        $signer4_email = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer4_email']);
        /*  $cc_name = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['cc_name']);
          $cc_email = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['cc_email']);*/
        $target_file = self::DEMO_DOCS_PATH . basename($_FILES["upload"]["name"]);
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $uploadOk = 1;
        // Check file size
        if (isset($_POST["submit"])) {
            if ($_FILES["upload"]["size"] > 500000) {
                echo "Sorry, your file is too large.";
                $uploadOk = 0;
            }
            if ($file_type != "pdf") {
                echo "Only PDF files are allowed";
                $uploadOk = 0;
            }
        }
        $envelope_args = [
            'signer_email' => $signer_email,
            'signer_name' => $signer_name,
            /*'cc_email' => $cc_email,
            'cc_name' => $cc_name,*/
            'signer2_email' => $signer2_email,
            'signer2_name' => $signer2_name,
            'signer3_email' => $signer3_email,
            'signer3_name' => $signer3_name,
            'signer4_email' => $signer4_email,
            'signer4_name' => $signer4_name,
            'target_file' => $target_file,
            'status' => 'sent'
        ];
        $args = [
            'account_id' => $_SESSION['ds_account_id'],
            'base_path' => $_SESSION['ds_base_path'],
            'ds_access_token' => $_SESSION['ds_access_token'],
            'envelope_args' => $envelope_args,
            'target_file' => $target_file,
            'file_type' => $file_type,
            'uploadOk' => $uploadOk
        ];

        return $args;
    }
}
