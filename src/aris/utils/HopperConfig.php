<?php
declare(strict_types=1);
namespace aris\utils;

use InvalidArgumentException;

class HopperConfig{
	/** @var int */
	private $transfer_tick_rate;

	/** @var int */
	private $transfer_per_tick;

	/** @var int */
	private $item_sucking_tick_rate;

	/** @var int */
	private $item_sucking_per_tick;
	
	/** @var int */
	private $limit;

	public function __construct(int $transfer_tick_rate, int $transfer_per_tick, int $item_sucking_tick_rate, int $item_sucking_per_tick, int $limit){
		$this->setTransferTickRate($transfer_tick_rate);
		$this->setTransferPerTick($transfer_per_tick);
		$this->setItemSuckingTickRate($item_sucking_tick_rate);
		$this->setItemSuckingPerTick($item_sucking_per_tick);
		$this->setLimit($limit);
	}

	public function getTransferTickRate() : int{
		return $this->transfer_tick_rate;
	}
	
	public function setTransferTickRate(int $transfer_tick_rate){
		if($transfer_tick_rate <= 0){
			throw new InvalidArgumentException("transfer_tick_rate cannot be <= 0, got {$transfer_tick_rate}");
		}
		$this->transfer_tick_rate = $transfer_tick_rate;
	}

	public function getTransferPerTick() : int{
		return $this->transfer_per_tick;
	}
	
	public function setTransferPerTick(int $transfer_per_tick){
		$this->transfer_per_tick = $transfer_per_tick;
	}

	public function getItemSuckingTickRate() : int{
		return $this->item_sucking_tick_rate;
	}

	public function setItemSuckingTickRate(int $item_sucking_tick_rate){
		if($item_sucking_tick_rate < 0){
			throw new InvalidArgumentException("item_sucking_tick_rate cannot be < 0, got {$item_sucking_tick_rate}");
		}
		$this->item_sucking_tick_rate = $item_sucking_tick_rate;
	}

	public function getItemSuckingPerTick() : int{
		return $this->item_sucking_per_tick;
	}
	
	public function setItemSuckingPerTick(int $item_sucking_per_tick){
		$this->item_sucking_per_tick = $item_sucking_per_tick;
	}
	
	public function getLimit() : int{
		return $this->limit;
	}
	
	public function setLimit(int $limit){
		if($limit < 0){
			throw new InvalidArgumentException("limit cannot be < 0, got {$limit}");
		}
		$this->limit = $limit;
	}
}