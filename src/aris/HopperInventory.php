<?php
namespace aris;
use pocketmine\inventory\ContainerInventory;
use pocketmine\network\mcpe\protocol\types\WindowTypes;

class HopperInventory extends ContainerInventory {

	public function __construct(HopperTile $tile){
		parent::__construct($tile);
	}
	
	public function getNetworkType() : int {
		return WindowTypes::HOPPER;
	}
	
	public function getName() : string {
		return "Hopper";
	}
	
	public function getDefaultSize() : int {
		return 5;
	}
}