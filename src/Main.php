<?php

declare(strict_types=1);

namespace VaxPex;

use pocketmine\block\BlockFactory;
use pocketmine\event\Listener;
use pocketmine\item\StringToItemParser;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\plugin\PluginBase;
use VaxPex\block\NetheriteBlock;

class Main extends PluginBase implements Listener {

	protected function onEnable(): void{
		$legacyIdMap = json_decode(file_get_contents(\pocketmine\BEDROCK_DATA_PATH . "block_id_map.json"), true);
		$runtimeBlockMapping = RuntimeBlockMapping::getInstance();
		$metaMap = [];

		foreach($runtimeBlockMapping->getBedrockKnownStates() as $runtimeId => $state){
			$name = $state->getString("name");
			if(!isset($legacyIdMap[$name])){
				continue;
			}

			$legacyId = $legacyIdMap[$name];
			if($legacyId <= 469){
				continue;
			}elseif(!isset($metaMap[$legacyId])){
				$metaMap[$legacyId] = 0;
			}

			$meta = $metaMap[$legacyId]++;
			if($meta > 15){
				continue;
			}

			$ref = new \ReflectionMethod($runtimeBlockMapping, "registerMapping");
			$ref->setAccessible(true);
			$ref->invoke($runtimeBlockMapping, $runtimeId, $legacyId, $meta);
		}

		StringToItemParser::getInstance()->registerBlock("netherite_block", fn() => BlockFactory::getInstance()->get(525, 0));

		BlockFactory::getInstance()->register(new NetheriteBlock);
	}
}
