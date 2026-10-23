<?php
declare(strict_types=1);

const MAX_LOOPS = 8;
const MAX_LEVEL = 3;

function levels(): array
{
    return [
        1 => [
            'name' => 'After Hours',
            'map' => [
                '###############',
                '#E............#',
                '#.#####.#####.#',
                '#.....B.......#',
                '#.#####D#####.#',
                '#.............#',
                '#.#####.#####.#',
                '#...........V.#',
                '#.#####.#####.#',
                '#.............#',
                '###############',
            ],
            'start' => [1, 9],
            'guards' => [
                [
                    'x' => 7, 'y' => 5, 'dir' => 'E',
                    'route' => [[7,5],[11,5],[11,7],[7,7],[3,7],[3,5]],
                    'route_index' => 0,
                    'range' => 4,
                ],
            ],
            'cameras' => [
                ['x' => 13, 'y' => 3, 'dir' => 'W', 'range' => 6],
            ],
        ],
        2 => [
            'name' => 'Gallery Two',
            'map' => [
                '###############',
                '#E....#.......#',
                '#.###.#.#####.#',
                '#...#.#.....#.#',
                '#.B.#.#####.#.#',
                '#...D.......#.#',
                '#####.#####.#.#',
                '#.....#...#.#.#',
                '#.#####.#.#.#.#',
                '#.......#...V.#',
                '###############',
            ],
            'start' => [1, 9],
            'guards' => [
                [
                    'x' => 3, 'y' => 3, 'dir' => 'S',
                    'route' => [[3,3],[3,5],[1,5],[1,3]],
                    'route_index' => 0,
                    'range' => 4,
                ],
                [
                    'x' => 9, 'y' => 7, 'dir' => 'N',
                    'route' => [[9,7],[11,7],[11,5],[9,5]],
                    'route_index' => 0,
                    'range' => 4,
                ],
            ],
            'cameras' => [
                ['x' => 7, 'y' => 3, 'dir' => 'S', 'range' => 5],
                ['x' => 13, 'y' => 5, 'dir' => 'S', 'range' => 4],
            ],
        ],
        3 => [
            'name' => 'The Archive',
            'map' => [
                '###############',
                '#E....#.......#',
                '#.###.#.###.#.#',
                '#...#.#...#.#.#',
                '###.#.###.#.#.#',
                '#B..D.....#...#',
                '#.#####.###.#.#',
                '#.....#.....#.#',
                '#.###.#####.#.#',
                '#...........V.#',
                '###############',
            ],
            'start' => [1, 9],
            'guards' => [
                [
                    'x' => 2, 'y' => 5, 'dir' => 'E',
                    'route' => [[2,5],[4,5],[4,3],[2,3]],
                    'route_index' => 0,
                    'range' => 4,
                ],
                [
                    'x' => 8, 'y' => 5, 'dir' => 'W',
                    'route' => [[8,5],[6,5],[6,7],[10,7],[10,5]],
                    'route_index' => 0,
                    'range' => 5,
                ],
                [
                    'x' => 12, 'y' => 9, 'dir' => 'N',
                    'route' => [[12,9],[12,7],[10,7],[10,9]],
                    'route_index' => 0,
                    'range' => 4,
                ],
            ],
            'cameras' => [
                ['x' => 5, 'y' => 1, 'dir' => 'S', 'range' => 4],
                ['x' => 13, 'y' => 3, 'dir' => 'S', 'range' => 5],
                ['x' => 7, 'y' => 9, 'dir' => 'N', 'range' => 4],
            ],
        ],
    ];
}

function createGame(): array
{
    $game = [
        'level' => 1,
        'status' => 'playing',
        'loops_used' => 1,
        'turn' => 0,
        'current_actions' => [],
        'ghost_runs' => [],
        'loot' => false,
        'message' => 'Enter the museum. Your failed runs will come back as ghosts.',
        'settled' => false,
    ];

    loadLevel($game, 1, false);
    return $game;
}

function loadLevel(array &$game, int $level, bool $preserveStats = true): void
{
    $defs = levels();
    $def = $defs[$level];

    $game['level'] = $level;
    $game['level_name'] = $def['name'];
    $game['map'] = array_map('str_split', $def['map']);
    $game['start'] = ['x' => $def['start'][0], 'y' => $def['start'][1]];
    $game['player'] = $game['start'];
    $game['guards'] = $def['guards'];
    $game['cameras'] = $def['cameras'];
    $game['turn'] = 0;
    $game['current_actions'] = [];
    $game['ghost_runs'] = [];
    $game['ghosts'] = [];
    $game['loot'] = false;
    $game['loops_used'] = 1;
    $game['status'] = 'playing';
    $game['message'] = "Level {$level}: {$def['name']}. Steal the target, then escape.";
    resetGhostPositions($game);
}

function performTurn(array &$game, string $action): void
{
    if ($game['status'] !== 'playing') {
        return;
    }

    $allowed = ['up','down','left','right','wait'];
    if (!in_array($action, $allowed, true)) {
        return;
    }

    $game['current_actions'][] = $action;

    applyActorAction($game, $game['player'], $action, true);

    foreach ($game['ghosts'] as $index => &$ghost) {
        $ghostAction = $game['ghost_runs'][$index][$game['turn']] ?? 'wait';
        applyActorAction($game, $ghost, $ghostAction, false);
    }
    unset($ghost);

    $doorOpen = isButtonPressed($game);

    if (actorOnTile($game, $game['player'], 'V')) {
        $game['loot'] = true;
        $game['map'][$game['player']['y']][$game['player']['x']] = '.';
        $game['message'] = 'Target secured. Get back to the exit.';
    }

    if (actorOnTile($game, $game['player'], 'E')) {
        if ($game['loot']) {
            finishLevel($game);
            return;
        }

        $game['message'] = 'You still need the target.';
    }

    moveGuards($game, $doorOpen);

    if (playerCaught($game, $doorOpen)) {
        endCurrentLoop($game, 'Caught. The timeline rewinds — your last run remains.');
        return;
    }

    $game['turn']++;

    if ($game['message'] === '' || str_starts_with($game['message'], 'Turn ')) {
        $game['message'] = "Turn {$game['turn']}. Read the patrols and use your ghosts.";
    }
}

function manualLoopReset(array &$game): void
{
    if ($game['status'] !== 'playing') {
        return;
    }

    if (count($game['current_actions']) === 0) {
        $game['message'] = 'Make at least one move before creating a loop.';
        return;
    }

    endCurrentLoop($game, 'Loop committed. Your previous self is now repeating it.');
}

function endCurrentLoop(array &$game, string $message): void
{
    if (count($game['current_actions']) > 0) {
        $game['ghost_runs'][] = $game['current_actions'];
    }

    if (count($game['ghost_runs']) >= MAX_LOOPS - 1) {
        $game['status'] = 'lost';
        $game['message'] = 'The timeline collapses. You used every available loop.';
        return;
    }

    $game['loops_used'] = count($game['ghost_runs']) + 1;
    $game['player'] = $game['start'];
    $game['turn'] = 0;
    $game['current_actions'] = [];
    $game['loot'] = false;
    resetLevelDynamicState($game);
    resetGhostPositions($game);
    $game['message'] = $message;
}

function finishLevel(array &$game): void
{
    if ($game['level'] >= MAX_LEVEL) {
        $game['status'] = 'won';
        $game['message'] = 'The archive is yours. You escape before the loop closes.';
        return;
    }

    $next = $game['level'] + 1;
    loadLevel($game, $next);
    $game['message'] = "Heist complete. Level {$next}: {$game['level_name']}.";
}

function resetLevelDynamicState(array &$game): void
{
    $def = levels()[$game['level']];
    $game['map'] = array_map('str_split', $def['map']);
    $game['guards'] = $def['guards'];
    $game['cameras'] = $def['cameras'];
}

function resetGhostPositions(array &$game): void
{
    $game['ghosts'] = [];

    foreach ($game['ghost_runs'] as $_) {
        $game['ghosts'][] = [
            'x' => $game['start']['x'],
            'y' => $game['start']['y'],
        ];
    }
}

function applyActorAction(array &$game, array &$actor, string $action, bool $isPlayer): void
{
    [$dx, $dy] = match ($action) {
        'up' => [0, -1],
        'down' => [0, 1],
        'left' => [-1, 0],
        'right' => [1, 0],
        default => [0, 0],
    };

    if ($dx === 0 && $dy === 0) {
        if ($isPlayer) {
            $game['message'] = 'You wait while the timeline advances.';
        }
        return;
    }

    $nx = $actor['x'] + $dx;
    $ny = $actor['y'] + $dy;

    if (!cellExists($game, $nx, $ny)) {
        return;
    }

    $tile = $game['map'][$ny][$nx];

    if ($tile === '#') {
        if ($isPlayer) {
            $game['message'] = 'A wall blocks the route.';
        }
        return;
    }

    if ($tile === 'D' && !isButtonPressed($game)) {
        if ($isPlayer) {
            $game['message'] = 'The security door is closed. Someone must stand on the pressure plate.';
        }
        return;
    }

    $actor['x'] = $nx;
    $actor['y'] = $ny;

    if ($isPlayer && $tile === '.') {
        $game['message'] = '';
    }
}

function isButtonPressed(array $game): bool
{
    if (actorOnTile($game, $game['player'], 'B')) {
        return true;
    }

    foreach ($game['ghosts'] as $ghost) {
        if (actorOnTile($game, $ghost, 'B')) {
            return true;
        }
    }

    return false;
}

function actorOnTile(array $game, array $actor, string $tile): bool
{
    return ($game['map'][$actor['y']][$actor['x']] ?? '#') === $tile;
}

function moveGuards(array &$game, bool $doorOpen): void
{
    $occupied = [];

    foreach ($game['guards'] as $guard) {
        $occupied[keyOf($guard['x'], $guard['y'])] = true;
    }

    foreach ($game['guards'] as $index => $guard) {
        unset($occupied[keyOf($guard['x'], $guard['y'])]);

        $route = $guard['route'];
        $targetIndex = $guard['route_index'] % count($route);
        [$tx, $ty] = $route[$targetIndex];

        if ($guard['x'] === $tx && $guard['y'] === $ty) {
            $targetIndex = ($targetIndex + 1) % count($route);
            $game['guards'][$index]['route_index'] = $targetIndex;
            [$tx, $ty] = $route[$targetIndex];
        }

        $step = oneStepToward($game, $guard['x'], $guard['y'], $tx, $ty, $doorOpen, $occupied);

        if ($step !== null) {
            $dx = $step[0] - $guard['x'];
            $dy = $step[1] - $guard['y'];

            $game['guards'][$index]['dir'] = directionFromDelta($dx, $dy);
            $game['guards'][$index]['x'] = $step[0];
            $game['guards'][$index]['y'] = $step[1];
        }

        $g = $game['guards'][$index];
        $occupied[keyOf($g['x'], $g['y'])] = true;
    }
}

function oneStepToward(
    array $game,
    int $sx,
    int $sy,
    int $tx,
    int $ty,
    bool $doorOpen,
    array $occupied
): ?array {
    $choices = [];

    foreach ([[0,-1],[1,0],[0,1],[-1,0]] as [$dx, $dy]) {
        $nx = $sx + $dx;
        $ny = $sy + $dy;

        if (!guardCanEnter($game, $nx, $ny, $doorOpen, $occupied)) {
            continue;
        }

        $distance = abs($tx - $nx) + abs($ty - $ny);
        $choices[] = [$distance, $nx, $ny];
    }

    if (!$choices) {
        return null;
    }

    usort($choices, fn($a, $b) => $a[0] <=> $b[0]);
    return [$choices[0][1], $choices[0][2]];
}

function guardCanEnter(array $game, int $x, int $y, bool $doorOpen, array $occupied): bool
{
    if (!cellExists($game, $x, $y)) {
        return false;
    }

    $tile = $game['map'][$y][$x];

    if ($tile === '#') {
        return false;
    }

    if ($tile === 'D' && !$doorOpen) {
        return false;
    }

    if (isset($occupied[keyOf($x, $y)])) {
        return false;
    }

    return true;
}

function playerCaught(array $game, bool $doorOpen): bool
{
    foreach ($game['guards'] as $guard) {
        if (
            $guard['x'] === $game['player']['x'] &&
            $guard['y'] === $game['player']['y']
        ) {
            return true;
        }

        if (observerSees(
            $game,
            $guard['x'],
            $guard['y'],
            $guard['dir'],
            $guard['range'],
            $game['player']['x'],
            $game['player']['y'],
            $doorOpen
        )) {
            return true;
        }
    }

    foreach ($game['cameras'] as $camera) {
        if (observerSees(
            $game,
            $camera['x'],
            $camera['y'],
            $camera['dir'],
            $camera['range'],
            $game['player']['x'],
            $game['player']['y'],
            $doorOpen
        )) {
            return true;
        }
    }

    return false;
}

function observerSees(
    array $game,
    int $ox,
    int $oy,
    string $dir,
    int $range,
    int $px,
    int $py,
    bool $doorOpen
): bool {
    [$dx, $dy] = match ($dir) {
        'N' => [0,-1],
        'E' => [1,0],
        'S' => [0,1],
        default => [-1,0],
    };

    for ($i = 1; $i <= $range; $i++) {
        $x = $ox + $dx * $i;
        $y = $oy + $dy * $i;

        if (!cellExists($game, $x, $y)) {
            return false;
        }

        $tile = $game['map'][$y][$x];

        if ($tile === '#') {
            return false;
        }

        if ($tile === 'D' && !$doorOpen) {
            return false;
        }

        if ($x === $px && $y === $py) {
            return true;
        }
    }

    return false;
}

function visionCells(array $game): array
{
    $doorOpen = isButtonPressed($game);
    $cells = [];

    foreach ($game['guards'] as $guard) {
        addVisionLine(
            $game,
            $cells,
            $guard['x'],
            $guard['y'],
            $guard['dir'],
            $guard['range'],
            $doorOpen
        );
    }

    foreach ($game['cameras'] as $camera) {
        addVisionLine(
            $game,
            $cells,
            $camera['x'],
            $camera['y'],
            $camera['dir'],
            $camera['range'],
            $doorOpen
        );
    }

    return $cells;
}

function addVisionLine(
    array $game,
    array &$cells,
    int $x,
    int $y,
    string $dir,
    int $range,
    bool $doorOpen
): void {
    [$dx, $dy] = match ($dir) {
        'N' => [0,-1],
        'E' => [1,0],
        'S' => [0,1],
        default => [-1,0],
    };

    for ($i = 1; $i <= $range; $i++) {
        $nx = $x + $dx * $i;
        $ny = $y + $dy * $i;

        if (!cellExists($game, $nx, $ny)) {
            break;
        }

        $tile = $game['map'][$ny][$nx];

        if ($tile === '#') {
            break;
        }

        $cells[keyOf($nx, $ny)] = true;

        if ($tile === 'D' && !$doorOpen) {
            break;
        }
    }
}

function guardAt(array $game, int $x, int $y): ?array
{
    foreach ($game['guards'] as $guard) {
        if ($guard['x'] === $x && $guard['y'] === $y) {
            return $guard;
        }
    }

    return null;
}

function cameraAt(array $game, int $x, int $y): ?array
{
    foreach ($game['cameras'] as $camera) {
        if ($camera['x'] === $x && $camera['y'] === $y) {
            return $camera;
        }
    }

    return null;
}

function ghostCountAt(array $game, int $x, int $y): int
{
    $count = 0;

    foreach ($game['ghosts'] as $ghost) {
        if ($ghost['x'] === $x && $ghost['y'] === $y) {
            $count++;
        }
    }

    return $count;
}

function tileView(array $game, int $x, int $y, array $vision): array
{
    if ($game['player']['x'] === $x && $game['player']['y'] === $y) {
        return ['player', '●', 'Current you'];
    }

    $ghosts = ghostCountAt($game, $x, $y);

    if ($ghosts > 0) {
        return ['ghost', $ghosts > 1 ? (string)$ghosts : '○', "{$ghosts} timeline ghost(s)"];
    }

    $guard = guardAt($game, $x, $y);
    if ($guard !== null) {
        return ['guard', directionGlyph($guard['dir']), 'Guard'];
    }

    $camera = cameraAt($game, $x, $y);
    if ($camera !== null) {
        return ['camera', cameraGlyph($camera['dir']), 'Camera'];
    }

    $tile = $game['map'][$y][$x];
    $visionClass = isset($vision[keyOf($x, $y)]) ? ' vision' : '';

    return match ($tile) {
        '#' => ['wall', '', 'Wall'],
        'B' => ['button' . $visionClass, '◆', 'Pressure plate'],
        'D' => [
            (isButtonPressed($game) ? 'door open' : 'door') . $visionClass,
            isButtonPressed($game) ? '▱' : '▥',
            isButtonPressed($game) ? 'Open security door' : 'Closed security door'
        ],
        'V' => ['vault' . $visionClass, '✦', 'Target'],
        'E' => ['exit' . $visionClass, '⇥', 'Exit'],
        default => ['floor' . $visionClass, '', isset($vision[keyOf($x, $y)]) ? 'Observed floor' : 'Floor'],
    };
}

function directionGlyph(string $dir): string
{
    return match ($dir) {
        'N' => '▲',
        'E' => '▶',
        'S' => '▼',
        default => '◀',
    };
}

function cameraGlyph(string $dir): string
{
    return match ($dir) {
        'N' => '△',
        'E' => '▷',
        'S' => '▽',
        default => '◁',
    };
}

function directionFromDelta(int $dx, int $dy): string
{
    if ($dx > 0) return 'E';
    if ($dx < 0) return 'W';
    if ($dy > 0) return 'S';
    return 'N';
}

function cellExists(array $game, int $x, int $y): bool
{
    return isset($game['map'][$y][$x]);
}

function keyOf(int $x, int $y): string
{
    return $x . ',' . $y;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
