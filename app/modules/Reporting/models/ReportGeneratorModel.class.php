<?php

class Reporting_ReportGeneratorModel extends ReportingBaseModel {
    /**
     * @var JasperResourceDescriptor
     */
    private $__report = null;
    
    /**
     * @var string
     */
    private $__format = null;
    
    /**
     * @var array
     */
    private $__parameters = array ();
    
    /**
     * @var string
     */
    private $__data = null;
    
    /**
     * @var JasperSoapMultipartClient
     */
    private $__client = null;
    
    public function initialize(AgaviContext $context, array $parameters = array()) {
        parent::initialize($context, $parameters);
        
        $this->__report = $this->getParameter('report');
        $this->__client = $this->getParameter('client');
        $this->__format = $this->getParameter('format', 'pdf');
        $this->__parameters = $this->getParameter('parameters', array ());
        
        if (!$this->__report instanceof JasperResourceDescriptor) {
            throw new AppKitModelException('report must be instance of JasperResourceDescriptor');
        }
        
        if (!$this->__client instanceof JasperSoapMultipartClient) {
            throw new AppKitModelException('client must be instance of SoapClient');
        };
        
    }
    
    public function getFormat() {
        return strtoupper($this->__format);
    }
    
    public function getReportData() {
        $uri = $this->__report->getResourceDescriptor()->getParameter(JasperResourceDescriptor::DESCRIPTOR_ATTR_URI);
        $request = new JasperRequestXmlDoc('runReport');
        $request->setArgument('RUN_OUTPUT_FORMAT', $this->getFormat());
        
        if ($this->getFormat() === 'HTML') {
            $request->setArgument('RUN_OUTPUT_IMAGES_URI', 'base64_inline_image:');
            $request->setArgument('IMAGES_URI', 'base64_inline_image:');
        }
        
        $request->setResourceDescriptor(JasperRequestXmlDoc::DESCRIPTOR_ATTR_URI, $uri);
        foreach ($this->__parameters as $pName=>$pValue) {
            $request->setParameter($pName, $pValue);
        }
        
        $this->runReport($request);
        
        /**
         * Insert foreign image data as base64 inline images that
         * the html report could be viewed stand alone without
         * additional resources
         */
        if ($this->getFormat() === 'HTML') {
            $this->htmlInsertInlineImages();
        }
        
        return $this->__data;
    }
    
    private function htmlInsertInlineImages() {
        $content = &$this->__data;
        $matches = array ();
        while (preg_match('/base64_inline_image:(\w+)/', $content, $matches)) {
            $cid = $matches[1];
            if ($this->__client->hasContentId($cid)) {
                $data_string = sprintf(
                	'data:%s;base64,%s', 
                    $this->__client->getHeaderFor($cid, 'content-type'), 
                    base64_encode($this->__client->getDataFor($cid))
                );
                $content = preg_replace('/'. $matches[0]. '/', $data_string, $content);
            } 
        }
    }
    
    private function runReport(JasperRequestXmlDoc $doc) {
        $this->__client->doRequest($doc);
        $this->__data = $this->__client->getDataFor(JasperSoapMultipartClient::CONTENT_ID_REPORT);
        return true;
    }
}

?>