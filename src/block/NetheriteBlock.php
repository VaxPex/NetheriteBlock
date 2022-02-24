<?php

declare(strict_types=1);

namespace VaxPex\block;

use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;

class NetheriteBlock extends Block {

	public function __construct(){
		parent::__construct(new BlockIdentifier(525, 0, -270),
			"Block of Netherite",
			new BlockBreakInfo(50, BlockToolType::PICKAXE, 5, 6000));
	}
}