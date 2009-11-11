<?php
/**
 * @author Rainer Spittel (rainer at silverstripe dot com)
 * @package openlayers
 * @subpackage code
 */

/**
 *
 */
class OLMapPage extends Page {
	
	static $db = array(
		'MapName' => 'Varchar(100)',
		'Latitude' => 'Decimal(12,8)',
		'Longitude' => 'Decimal(12,8)',
		'DefaultZoom' => 'Int'
	);	
	
	static $has_many = array(
		'Property' => 'OLMapProperty',
		'Layers' => 'OLLayer'
	);

	/**
	 * Overwrites SiteTree.getCMSFields to change the CMS form behaviour, 
	 *  i.e. by adding form fields for the additional attributes defined in 
	 * {@link OpenLayersPage::$db}.
	 */ 
	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab("Root.Content.Main", new TextField("Latitude", "Latitude"),"Content");
		$fields->addFieldToTab("Root.Content.Main", new TextField("Longitude", "Longitude"),"Content");
		$fields->addFieldToTab("Root.Content.Main", new TextField("DefaultZoom", "Default Zoom"),"Content");
		$propertyTablefield = new ComplexTableField(
			$this,
			'OLMapProperty',
			'OLMapProperty',
			array(
				'Name' => 'Property Name',
				'Value' => 'Property Value'
			),
			'getCMSFields_forPopup',
			'',
			'Created'
			);
			$fields->addFieldToTab( 'Root.Content.Properties', $propertyTablefield );
			
		$layerTablefield = new ComplexTableField(
			$this,
			'Layers',
			'OLLayer',
			array(
				'Name' => 'Name',
				'Url' => 'URL',
				'Type' => 'Layer Type',
				'ogc_transparent' => 'Transparent',
				'Enabled' => 'Enabled',
				'Visible' => 'Visible',
				'Queryable' => 'Queryable'	
			),
			'getCMSFields_forPopup',
			'',
			'Created'
			);
			$fields->addFieldToTab( 'Root.Content.Layers', $layerTablefield );
							
		return $fields;
	}

	/**
	 * Serialise the data structure into an array.
	 */
	function serialise() {
		$data   = $this->toMap();
		
		// get all layers of this map in the order of 'DisplayPriority ASC'
		$layers = $this->getComponents('Layers',null,'DisplayPriority ASC');
		
		$result = array();
		$data   = array();
		
		$data['Name'] = $this->getField('MapName');
		$data['Latitude'] = $this->getField('Latitude');
		$data['Longitude'] = $this->getField('Longitude');
		$data['DefaultZoom'] = $this->getField('DefaultZoom');
		$data['PageName'] = $this->getField('URLSegment');
		$result['Map'] = $data;
		
		$data   = array();
		foreach($layers as $layer) {
			$data[] = $layer->serialise();
		}
		$result['Layer'] = $data;
		return $result;
	}

}

/**
 * Controller Class for Main OpenLayers Page
 *
 * Page controller class for OpenLayersPage (@link OpenLayersPage). The controller
 * class handles the requests and delegates the requests to the page instance
 * as well as to the available OGC webservices.
 */
class OLMapPage_Controller extends Page_Controller {
	
	/**
	 * varaible to store the open layers instance in the controller class.
	 * @var OpenLayers openLayers
	 */
	protected $openLayers = null;

	/**
	 * Returns the open layers instance (via singleton pattern).
	 *
	 * @return OpenLayers model class for the open layers implementation.
	 */
	function getOpenLayers() {
		if ($this->openLayers == null) {
			$this->openLayers = new OpenLayersModel();
		}
		return $this->openLayers;
	}

	/**
	 * Initialisation function that is run before any action on the controller is called.
	 */
	public function init() {
		
		parent::init();

		$openLayers = $this->getOpenLayers();
		$mapPage    = $this->data();
		
		Requirements::javascript( $openLayers->getRequiredJavaScript() );		
		Requirements::javascript(THIRDPARTY_DIR . "/jquery/jquery.js");

		Requirements::javascript('openlayers/javascript/OLMapPage.js');

		// old js mockup			
		// Requirements::javascript('openlayers/javascript/OpenLayersPage.js');


		// serialize map cofiguration
		$config     = $mapPage->serialise();
		$jsConfig = "var ss_config = ".json_encode($config);;

		Requirements::customScript($jsConfig);
		
	}
	
	/**
	 * Returns the HTML response for the map popup-box. After the user clicks
	 * on the map, the CMS will send of a request to the OGC server to request
	 * a XML data structure for the features on that selected location, parses
	 * the XML response and renders the HTML, which will be returned to the
	 * popup window.
	 *
	 * @param Request $data
	 *
	 * @return string HTML segment
	 */
	public function doGetFeatureInfo( $data ) {
		
		$params = $data->getVars();
		
		$layername = Convert::raw2sql($params['LAYERS']);

		$page = $this->data();
		
		$layerSet = $page->getComponents('Layers',"Name = '{$layername}'");
		$layer = $layerSet->First();

		// this params are static, so we don't need to send them from js //
		$staticParams = array(
			'REQUEST' => 'GetFeatureInfo', 
			'INFO_FORMAT' => 'application/vnd.ogc.gml', 
			'VERSION' => '1.1.1', 
			'TRANSPARENT' => 'true', 
			'STYLE' => '', 
			'EXCEPTIONS' => 'application%2Fvnd.ogc.se_xml', 
			'FORMAT' => 'image%2Fpng',
			'SRS' => 'EPSG%3A4326'
		);
		$vars = $data->getVars();
		$URLRequest = '';
		foreach($vars as $k => $v) {
			if($k != 'url') $URLRequest .= $k.'='.$v.'&';
		}
		foreach($staticParams as $k => $v){
			$URLRequest .= $k.'='.$v.'&';
		}
		$URLRequest = trim($URLRequest,"&");
		$URLRequest = str_replace('RequestURL=','',$URLRequest);
		
		// SEND REQUEST //
		$session = curl_init($URLRequest);
		curl_setopt($session, CURLOPT_HEADER, false);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		$xml = curl_exec($session);
		header("Content-Type: text/xml");
		curl_close($session);
		
		return $xml;
	}
	
	
}
