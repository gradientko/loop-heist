<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['loop_heist_stats'])) {
    $_SESSION['loop_heist_stats'] = [
        'runs' => 0,
        'wins' => 0,
        'best_level' => 1,
        'best_loops' => null,
        'best_turns' => null,
    ];
}

if (!isset($_SESSION['loop_heist_game'])) {
    $_SESSION['loop_heist_game'] = createGame();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request token.';
    } else {
        $action = $_POST['action'] ?? '';
        $game = $_SESSION['loop_heist_game'];

        if ($action === 'new') {
            $_SESSION['loop_heist_game'] = createGame();
            header('Location: index.php');
            exit;
        }

        if ($game['status'] === 'playing') {
            if ($action === 'reset_loop') {
                manualLoopReset($game);
            } else {
                performTurn($game, (string)$action);
            }

            $_SESSION['loop_heist_stats']['best_level'] = max(
                $_SESSION['loop_heist_stats']['best_level'],
                $game['level']
            );

            if ($game['status'] !== 'playing' && empty($game['settled'])) {
                $_SESSION['loop_heist_stats']['runs']++;

                if ($game['status'] === 'won') {
                    $_SESSION['loop_heist_stats']['wins']++;

                    $bestLoops = $_SESSION['loop_heist_stats']['best_loops'];
                    if ($bestLoops === null || $game['loops_used'] < $bestLoops) {
                        $_SESSION['loop_heist_stats']['best_loops'] = $game['loops_used'];
                    }

                    $totalTurns = array_sum(array_map('count', $game['ghost_runs'])) + count($game['current_actions']);
                    $bestTurns = $_SESSION['loop_heist_stats']['best_turns'];

                    if ($bestTurns === null || $totalTurns < $bestTurns) {
                        $_SESSION['loop_heist_stats']['best_turns'] = $totalTurns;
                    }
                }

                $game['settled'] = true;
            }

            $_SESSION['loop_heist_game'] = $game;
        }

        header('Location: index.php');
        exit;
    }
}

$game = $_SESSION['loop_heist_game'];
$stats = $_SESSION['loop_heist_stats'];
$vision = visionCells($game);
$height = count($game['map']);
$width = count($game['map'][0]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Loop Heist — Pure PHP</title>
  <meta name="description" content="A turn-based time-loop stealth heist written in pure PHP.">
  <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="shell">
  <header class="hero">
    <div>
      <span class="eyebrow">PURE PHP TIME-LOOP HEIST</span>
      <h1>Fail. Rewind. Cooperate.</h1>
      <p>
        Every failed attempt becomes a ghost that repeats your old moves.
        Use your past selves to hold switches, open doors and steal the target.
      </p>
    </div>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="new">
      <button class="text-button" type="submit">New heist</button>
    </form>
  </header>

  <section class="stats">
    <div><span>Level</span><strong><?= (int)$game['level'] ?>/<?= MAX_LEVEL ?></strong></div>
    <div><span>Loop</span><strong><?= (int)$game['loops_used'] ?>/<?= MAX_LOOPS ?></strong></div>
    <div><span>Turn</span><strong><?= (int)$game['turn'] ?></strong></div>
    <div><span>Ghosts</span><strong><?= count($game['ghost_runs']) ?></strong></div>
  </section>

  <section class="layout">
    <section class="game-card">
      <div class="game-head">
        <div>
          <span class="eyebrow">
            LEVEL <?= (int)$game['level'] ?> · <?= e($game['level_name']) ?>
          </span>
          <h2><?= e($game['message']) ?></h2>
        </div>

        <span class="status-pill <?= e($game['status']) ?>">
          <?= strtoupper(e($game['status'])) ?>
        </span>
      </div>

      <?php if ($error !== null): ?>
        <div class="notice"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="mission-strip">
        <div>
          <span>Target</span>
          <strong><?= $game['loot'] ? 'SECURED' : 'NOT YET' ?></strong>
        </div>
        <div>
          <span>Pressure plate</span>
          <strong><?= isButtonPressed($game) ? 'ACTIVE' : 'OFF' ?></strong>
        </div>
        <div>
          <span>Security door</span>
          <strong><?= isButtonPressed($game) ? 'OPEN' : 'LOCKED' ?></strong>
        </div>
      </div>

      <div class="map" style="--cols: <?= $width ?>;">
        <?php for ($y = 0; $y < $height; $y++): ?>
          <?php for ($x = 0; $x < $width; $x++): ?>
            <?php [$class, $glyph, $label] = tileView($game, $x, $y, $vision); ?>
            <div class="tile <?= e($class) ?>" title="<?= e($label) ?>">
              <?= e($glyph) ?>
            </div>
          <?php endfor; ?>
        <?php endfor; ?>
      </div>

      <?php if ($game['status'] === 'playing'): ?>
        <section class="controls">
          <div class="pad">
            <span></span>
            <?= actionButton('up', '↑', 'Move up') ?>
            <span></span>
            <?= actionButton('left', '←', 'Move left') ?>
            <?= actionButton('down', '↓', 'Move down') ?>
            <?= actionButton('right', '→', 'Move right') ?>
          </div>

          <div class="action-stack">
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="wait">
              <button type="submit" class="secondary-action">
                Wait one turn
                <span>Ghosts and guards still move</span>
              </button>
            </form>

            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="reset_loop">
              <button type="submit" class="loop-action">
                Commit loop
                <span>Create a ghost from this attempt</span>
              </button>
            </form>
          </div>
        </section>
      <?php else: ?>
        <form method="post" class="again">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="action" value="new">
          <button type="submit">
            <?= $game['status'] === 'won' ? 'Run the heist again' : 'Restart timeline' ?>
          </button>
        </form>
      <?php endif; ?>
    </section>

    <aside class="side">
      <section class="panel">
        <span class="eyebrow">TIMELINE</span>
        <div class="timeline">
          <?php if (!$game['ghost_runs']): ?>
            <p>No ghosts yet. Commit or fail a loop to create one.</p>
          <?php else: ?>
            <?php foreach ($game['ghost_runs'] as $i => $run): ?>
              <div>
                <strong>Ghost <?= $i + 1 ?></strong>
                <span><?= count($run) ?> recorded turns</span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="legend">
        <div><i class="player-mark">●</i><span>Current you</span></div>
        <div><i class="ghost-mark">○</i><span>Past loop</span></div>
        <div><i class="guard-mark">▲</i><span>Guard + direction</span></div>
        <div><i class="camera-mark">△</i><span>Camera + direction</span></div>
        <div><i class="vision-mark"></i><span>Observed tile</span></div>
        <div><i class="button-mark">◆</i><span>Pressure plate</span></div>
        <div><i class="door-mark">▥</i><span>Security door</span></div>
        <div><i class="vault-mark">✦</i><span>Target</span></div>
        <div><i class="exit-mark">⇥</i><span>Exit</span></div>
      </section>

      <section class="panel">
        <span class="eyebrow">HOW THE LOOP WORKS</span>
        <p>
          Each action you take is recorded. When the loop resets, that sequence becomes
          a ghost. On turn 1 every ghost performs its old turn 1 action, on turn 2 its old
          turn 2 action, and so on.
        </p>
      </section>

      <section class="panel">
        <span class="eyebrow">RECORDS</span>
        <dl>
          <div><dt>Runs</dt><dd><?= (int)$stats['runs'] ?></dd></div>
          <div><dt>Wins</dt><dd><?= (int)$stats['wins'] ?></dd></div>
          <div><dt>Best level</dt><dd><?= (int)$stats['best_level'] ?></dd></div>
          <div><dt>Best loops</dt><dd><?= $stats['best_loops'] ?? '—' ?></dd></div>
          <div><dt>Best turns</dt><dd><?= $stats['best_turns'] ?? '—' ?></dd></div>
        </dl>
      </section>

      <section class="panel">
        <span class="eyebrow">PURE SERVER SIDE</span>
        <p>
          No JavaScript. Replays, ghost synchronization, guards, cameras, doors,
          collision and session state are handled entirely in PHP.
        </p>
      </section>
    </aside>
  </section>

  <footer>
    <span>PHP sessions · deterministic replay · stealth · no JavaScript</span>
    <span>loop-heist</span>
  </footer>
</main>
</body>
</html>
<?php
function actionButton(string $action, string $label, string $aria): string
{
    $token = e($_SESSION['csrf_token']);

    return <<<HTML
<form method="post">
  <input type="hidden" name="csrf_token" value="{$token}">
  <input type="hidden" name="action" value="{$action}">
  <button type="submit" aria-label="{$aria}">{$label}</button>
</form>
HTML;
}
