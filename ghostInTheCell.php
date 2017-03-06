<?php
/**
 * Auto-generated code below aims at helping you parse
 * the standard input according to the problem statement.
 **/

function cmp($a, $b)
{
    if ($a['score'] == $b['score']) {
        return 0;
    }
    return ($a['score'] < $b['score']) ? 1 : -1;
}
function cmpDistance($a, $b)
{
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}

class game{
    public $step = 0;
    public $factories = array();
    public $troops = array();
    public $links = array();
    public $bombs = array();
    public $nbBombsAvailable = 2;
    public $factorybombedId;

    static public $ownerMapping = array(1 => 'me',0 => 'neutral', -1 => 'them');

    public function nextStepStarted() {
        $this->step++;
        $this->troops = array();
        foreach($this->bombs as $num => $bomb) {
            $bomb->seenThisTurn = false;
            if($bomb->exploded) $bomb->explosionRemainingTour--;
        }
    }
    public function addLink($factory1,$factory2,$distance) {
        
        $_links[] = new link($factory1,$factory2,$distance);
        if(!isset($this->factories[$factory1])) {
            $this->factories[$factory1] = new factory($factory1);
        }
        if(!isset($this->factories[$factory2])) {
            $this->factories[$factory2] = new factory($factory2);
        }

        $this->factories[$factory1]->addLinkedFactory($factory2,$distance);
        $this->factories[$factory2]->addLinkedFactory($factory1,$distance);
    }

    public function sortLinkedFactories() {
        foreach($this->factories as $num => $factory) $factory->sortLinkedFactories();
    }

    public function loadLine($entityId,$entityType,$arg1,$arg2,$arg3,$arg4,$arg5) {
        if($entityType == 'TROOP') $this->troops[] = new troop($entityId,$arg1,$arg2,$arg3,$arg4,$arg5);
        if($entityType == 'FACTORY') $this->factories[$entityId]->loadLine($arg1,$arg2,$arg3,$arg4);
        if($entityType == 'BOMB') {
            if(isset($this->bombs[$entityId])) $this->bombs[$entityId]->loadLine($arg1,$arg2,$arg3,$arg4);
            else {
                $this->bombs[$entityId] = new bomb($entityId);
                $this->bombs[$entityId]->loadLine($arg1,$arg2,$arg3,$arg4);
            }
        }
        error_log('load line: '.$entityType.':'.$entityId.' - '.$arg1.'|'.$arg2.'|'.$arg3.'|'.$arg4.'|'.$arg5);
    }

    public function log() {
        foreach($this->factories as $num => $factory) $factory->log();
        foreach($this->troops as $num => $troop) $troop->log();
        foreach($this->bombs as $num => $bomb) $bomb->log();
    }

    public function compute() {
        //We attach the bombs to the factories
        foreach($this->bombs as $num => $bomb) {
            if($bomb->owner == 'me' && $bomb->nbTourToDestination > 0 && $bomb->seenThisTurn) {
                $this->factories[$bomb->arrivalFactory]->attachBomb($bomb);
            }
        }

        //We compute the tours
        for($i=1;$i<=20;$i++) {
            //Avancée des troupes et bombes existantes
            foreach($this->troops as $num => $troop) $troop->handleMovement();


            //Exécution des ordres de chaque joueur
            //Production de nouveaux cyborgs dans chaque usine
            foreach($this->factories as $num => $factory) $factory->handleProduction();
            //Résolution des combats
            foreach($this->troops as $num => $troop) {
                if($troop->nbTourToDestination == 0) {
                    $this->factories[$troop->arrivalFactory]->handleCombat($troop);
                }
            }
            //Explosion des bombes
            //Vérification des conditions de fin
        } //finish calcul for 20 rounds

        //for each factory we aevaluate the action
        $possibleActions = array();
        foreach($this->factories as $num => $factory) {
            //We don t care of factories who don t produce
            if($factory->nbCyborgProduction == 0) continue;

            //the factory is mine and it goes to them, need to defend
            if($factory->owner == 'me' && $factory->outcomeOwner == 'them') {
                foreach($factory->linkedFactories as $factoryId => $distance) {
                    $testFactory = $this->factories[$factoryId];
                    if(in_array($testFactory->owner ,array('them','neutral'))) continue;
                    //the plant doesn t produce, we leave it
                    if($testFactory->nbCyborgProduction == 0) {
                        $possibleActions[] = array(
                            'action' => 'noProductionHelp',
                            'from' => $testFactory->id,
                            'to' => $factory->id,
                            'maxNbTroop' => $testFactory->nbCyborgs,
                            'score' => 100
                        );
                    }
                    //the plant is not going away, we can spare some soldier
                    if($testFactory->outcomeOwner == 'me') {
                        $possibleActions[] = array(
                            'action' => 'help',
                            'from' => $testFactory->id,
                            'to' => $factory->id,
                            'maxNbTroop' => $testFactory->outcomeMinTroop,
                            'score' => 60 + 10*$factory->nbCyborgProduction - 5*$testFactory->nbCyborgProduction-5*$distance+$testFactory->outcomeMinTroop-$factory->nbCyborgs);
                    }
                }
            }

            //We try to conquer their bases
            if($factory->owner == 'them' && $factory->outcomeOwner == 'them') {
                foreach($factory->linkedFactories as $factoryId => $distance) {
                    $testFactory = $this->factories[$factoryId];
                    if(in_array($testFactory->owner ,array('them','neutral'))) continue;

                    //the plant is not going away, we can spare some soldier
                    if($testFactory->outcomeOwner == 'me') {
                        $possibleActions[] = array(
                            'action' => 'attack',
                            'from' => $testFactory->id,
                            'to' => $factory->id,
                            'maxNbTroop' => $testFactory->outcomeMinTroop,
                            'score' => 30 + 10*$factory->nbCyborgProduction - 5*$testFactory->nbCyborgProduction-5*$distance+$testFactory->outcomeMinTroop-$factory->nbCyborgs);
                    }
                }
            }
            //we conquer the neutral
            if($factory->owner == 'neutral') {
                foreach($factory->linkedFactories as $factoryId => $distance) {
                    $testFactory = $this->factories[$factoryId];
                    if(in_array($testFactory->owner ,array('them','neutral'))) continue;
                    //no need to resend someone because I will own it
                    if($factory->outcomeOwner == 'me') continue;

                    //the plant is not going away, we can spare some soldier
                    if($testFactory->outcomeOwner == 'me') {
                        $possibleActions[] = array(
                            'action' => 'attackNeutral',
                            'from' => $testFactory->id,
                            'to' => $factory->id,
                            'maxNbTroop' => $testFactory->outcomeMinTroop,
                            'score' => 50+10*$factory->nbCyborgProduction - 5*$testFactory->nbCyborgProduction-5*$distance+$testFactory->outcomeMinTroop-$factory->nbCyborgs);
                    }
                }
            } 
            
        }

        //We log the current status
        $this->log();
        error_log('----------------------------------------');
        //We take the best action
        if(empty($possibleActions)) echo("WAIT\n");
        else {
            $actionReturned = false;
            //We sort by score
            usort($possibleActions, "cmp");
            $nbAttack = 0;
            foreach($possibleActions as $num => $action) {

                error_log('action '.$action['action'].'|'.$action['from'].'->'.$action['to'].' '.$action['maxNbTroop'].' - score: '.$action['score']);

                //need to check what we can send
                $availableTroops = $this->factories[$action['from']]->outcomeMinTroop;
                //need to check what is needed
                $needed = $this->factories[$action['to']]->outcomeNbTroops+2;
                if($needed <= 0) continue; 
                if($availableTroops <= 0) continue;
                if($availableTroops > $needed) {
                    $actionReturned = true;
                    error_log('MOVE '.$action['from'].' '.$action['to'].' '.$needed);
                    if($nbAttack > 0) echo ';';
                    echo 'MOVE '.$action['from'].' '.$action['to'].' '.$needed;
                    $this->factories[$action['from']]->outcomeMinTroop -= $needed;
                    $this->factories[$action['to']]->outcomeNbTroops -= $needed;
                    $nbAttack++;
                } else {
                    $actionReturned = true;
                    error_log('MOVE '.$action['from'].' '.$action['to'].' '.$availableTroops);
                    if($nbAttack > 0) echo ';';
                    echo 'MOVE '.$action['from'].' '.$action['to'].' '.$availableTroops;
                    $this->factories[$action['from']]->outcomeMinTroop -= $availableTroops;
                    $this->factories[$action['to']]->outcomeNbTroops -= $availableTroops;
                    $nbAttack++;
                }
                //we change the counter
                
            }


            foreach($this->factories as $num => $factory) {
                //increment
                if($factory->owner == 'me' && $factory->outcomeOwner == 'me' && $factory->nbCyborgs > 10 && $factory->nbCyborgProduction < 3) {
                    if($nbAttack > 0) echo ';';
                    echo 'INC '.$factory->id;
                    $factory->nbCyborgProduction++;
                    $nbAttack++;
                    $actionReturned = true;
                }  
                //bomb
                if($factory->owner == 'them' && $factory->outcomeOwner == 'them' && $factory->outcomeMinTroop > 10 && $factory->nbCyborgProduction > 1 && $this->nbBombsAvailable > 0)  {
                    if($this->nbBombsAvailable == 1 && $factory->id == $this->factorybombedId) continue;
                    //We look for the closest factory I own
                    $minDist = 500;
                    $senderId = '';
                    foreach($factory->linkedFactories as $factoryId => $distance) {
                        $testFactory = $this->factories[$factoryId];
                        if($testFactory->owner != 'me') continue;
                        if($distance < $minDist) {
                            $senderId = $testFactory->id;
                            $minDist = $distance;
                        }
                    }
                    if(!empty($senderId)) {
                        if($nbAttack > 0) echo ';';
                        echo 'BOMB '.$senderId.' '.$factory->id;
                        $this->nbBombsAvailable--;
                        $this->factorybombedId = $factory->id;
                        $nbAttack++;
                        $actionReturned = true;
                    }
                }
            }
            
            if($actionReturned) echo "\n";
            else echo("WAIT\n"); 
        } 
    } //end compute
} //end game


class factory{
    public $id;
    public $owner;
    public $nbCyborgs;
    public $nbCyborgProduction;
    public $linkedFactories = array();

    public $outcomeNbTroops;
    public $outcomeOwner;
    //what is the lowest point the owner of the factory goes through
    public $outcomeMinTroop;
    //first change, what would have prevented it
    public $preventOutcomeNbTroops = 'notSet';

    public $nbTourToProduction = 0;

    //If a bomb exploded on the factory
    public $bomb = '';

    public function __construct($id) {
        $this->id = $id;
    }
    public function addLinkedFactory($factoryId,$distance) {
        $this->linkedFactories[$factoryId] = $distance;
    }

    public function sortLinkedFactories() {
        uasort($this->linkedFactories, "cmpDistance");
    }

    public function attachBomb($bomb) {
        $this->bomb = $bomb;
    }
    public function loadLine($owner,$nbCyborgs,$nbCyborgProduction,$nbTourToProduction){
        $this->owner = game::$ownerMapping[$owner];
        $this->nbCyborgs = $nbCyborgs;
        $this->nbCyborgProduction = $nbCyborgProduction;
        $this->nbTourToProduction = $nbTourToProduction;


        $this->outcomeNbTroops = $nbCyborgs;
        $this->outcomeOwner = game::$ownerMapping[$owner];
        $this->outcomeMinTroop = $nbCyborgs;
        $this->preventOutcomeNbTroops = 'notSet';
    }
    public function handleProduction() {
        if($this->owner == 'neutral') return;

        if(!empty($this->bomb)) {
            if($this->bomb->nbTourToDestination == 0) $this->nbTourToProduction = 5;
            if($this->bomb->nbTourToDestination >= 0) $this->bomb->nbTourToDestination--;
        }

        if($this->nbTourToProduction >= 0) {
            $this->nbTourToProduction--;
            return;
        }

        $this->outcomeNbTroops += $this->nbCyborgProduction;
    }

    public function handleCombat($troop){
        if($this->outcomeOwner == 'neutral') $this->outcomeNbTroops -= $troop->nbCyborgs;
        elseif($this->outcomeOwner == 'me') {
            if($troop->owner == 'me') $this->outcomeNbTroops += $troop->nbCyborgs;
            else {
                $this->outcomeNbTroops -= $troop->nbCyborgs;
                if($this->outcomeNbTroops < $this->outcomeMinTroop ) $this->outcomeMinTroop = $this->outcomeNbTroops;
            }
        }
        elseif($this->outcomeOwner == 'them') {
            if($troop->owner == 'them') $this->outcomeNbTroops += $troop->nbCyborgs;
            else {
                $this->outcomeNbTroops -= $troop->nbCyborgs;
                if($this->outcomeNbTroops < $this->outcomeMinTroop ) $this->outcomeMinTroop = $this->outcomeNbTroops;
            }
        }
        //if the number is < 0 the owner change
        if($this->outcomeNbTroops < 0) {
            $this->outcomeNbTroops = -1 * $this->outcomeNbTroops;
            $this->outcomeOwner = $troop->owner;
            $this->outcomeMinTroop = $this->outcomeNbTroops;
            if($this->preventOutcomeNbTroops == 'notSet') $this->preventOutcomeNbTroops = $this->outcomeNbTroops;
        }
    }

    public function log() {
        error_log('factory'.$this->id.': '.$this->owner.' - nb:'.$this->nbCyborgs.' prod:'.$this->nbCyborgProduction.' outcome: '.$this->outcomeNbTroops.' - '.$this->outcomeOwner.' min:'.$this->outcomeMinTroop.' prevent: '.$this->preventOutcomeNbTroops);
    }
} //end factory



class bomb{
    public $id;
    public $owner;
    public $startFactory;
    public $arrivalFactory;
    public $nbTourToDestination;
    public $seenThisTurn;
    public $exploded = false;
    public $explosionRemainingTour = 5;
    public function __construct($id) {
        error_log('*****NEW BOMB DETECTED  '.$id.'****');
        $this->id = $id;
    }

    public function loadLine($owner,$startFactory,$arrivalFactory,$nbTourToDestination){
        $this->owner = game::$ownerMapping[$owner];
        $this->startFactory = $startFactory;
        $this->arrivalFactory = $arrivalFactory;
        $this->nbTourToDestination = $nbTourToDestination;
        $this->seenThisTurn = true;
    }



    public function log() {
        //we don t care if exploded for more than 5 tours
        if($this->explosionRemainingTour < 0) return;
        error_log('bomb'.$this->id.': '.$this->owner.' - start:'.$this->startFactory.' arrive:'.$this->arrivalFactory.' nbTours: '.$this->nbTourToDestination.' explosion: '.$this->exploded.' -> '.$this->explosionRemainingTour);
    }
}


class troop{
    public $id;
    public $owner;
    public $startFactory;
    public $arrivalFactory;
    public $nbCyborgs;
    public $nbTourToDestination;


    public function __construct($id,$owner,$startFactory,$arrivalFactory,$nbCyborgs,$nbTourToDestination) {
        $this->id = $id;
        $this->owner = game::$ownerMapping[$owner];
        $this->startFactory = $startFactory;
        $this->arrivalFactory = $arrivalFactory;
        $this->nbCyborgs = $nbCyborgs;
        $this->nbTourToDestination =$nbTourToDestination;
    }
    public function log() {
        //error_log('troop'.$this->id.': '.$this->owner.' - '.$this->startFactory.' -> '.$this->arrivalFactory.' nb:'.$this->nbCyborgs.' tours: '.$this->nbTourToDestination);
    }

    public function handleMovement() {
        $this->nbTourToDestination--;
    }
} //end factory


class link{
    public $factory1;
    public $factory2;
    public $distance;

    public function __construct($factory1,$factory2,$distance) {
        $this->factory1 = $factory1;
        $this->factory2 = $factory1;
        $this->distance = $distance;
        error_log('link '.$factory1.' -> '.$factory2.' = '.$distance);
    }
} //end link


error_log('game started');
$game = new game();

fscanf(STDIN, "%d",
    $factoryCount // the number of factories
);
fscanf(STDIN, "%d",
    $linkCount // the number of links between factories
);
for ($i = 0; $i < $linkCount; $i++)
{
    fscanf(STDIN, "%d %d %d",
        $factory1,
        $factory2,
        $distance
    );
    $game->addLink($factory1,$factory2,$distance);
}
//we put on top of the array the 
$game->sortLinkedFactories();
// game loop
while (TRUE)
{
    fscanf(STDIN, "%d",
        $entityCount // the number of entities (e.g. factories and troops)
    );

    $game->nextStepStarted();
    for ($i = 0; $i < $entityCount; $i++)
    {
        fscanf(STDIN, "%d %s %d %d %d %d %d",
            $entityId,
            $entityType,
            $arg1,
            $arg2,
            $arg3,
            $arg4,
            $arg5
        );
        $game->loadLine($entityId,$entityType,$arg1,$arg2,$arg3,$arg4,$arg5);
    }

    // Any valid action, such as "WAIT" or "MOVE source destination cyborgs"
    $game->compute();
    
}
?>