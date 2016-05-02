<?php
	
namespace smtech\ReflexiveCanvasLTI;

use Battis\BatchAdmin\BatchManager;

class Installer extends BatchManager {
	
	public function __construct($secrets) {
		$config = new ImportConfigXMLAction($secrets);
		$sql = new ImportMySQLSchemaConfigurableAction(
			new ConfigXMLReplaceableData(
				ImportConfigXMLAction::CONFIG
			)
		);
	}
}