# loop-heist

A turn-based time-loop stealth game written in pure PHP.

There is **no JavaScript**.

Every action in the current attempt is recorded. When you are caught or manually commit the loop, that attempt becomes a ghost.

On the next attempt the ghost starts at the same position and repeats the old sequence one turn at a time.

That means you can cooperate with your own past.

## Core idea

Imagine this sequence:

```text
Loop 1
turn 1  move right
turn 2  move right
turn 3  move up
turn 4  stand on pressure plate
turn 5  wait
turn 6  wait
```

Commit the loop.

Now Loop 2 begins.

When current-you reaches turn 4, Ghost 1 also reaches its old turn 4 and stands on the pressure plate.

The security door opens.

Current-you can use that moment to pass through.

## Features

- pure PHP
- no JavaScript
- deterministic time-loop replay
- previous attempts become ghosts
- multiple simultaneous ghosts
- pressure plates
- security doors
- guards
- guard patrol routes
- directional guard vision
- static security cameras
- visible surveillance lines
- vault target
- escape objective
- manual loop commit
- automatic rewind when caught
- 8-loop limit
- 3 handcrafted levels
- session statistics
- best completed loop count
- best turn count
- CSRF protection
- POST / Redirect / GET
- no framework
- no database

## Requirements

PHP 8.1+

## Run

```bash
php -S localhost:8000
```

Open:

```text
http://localhost:8000
```

## Controls

All controls are ordinary HTML forms.

```text
↑  move up
↓  move down
←  move left
→  move right
```

You can also:

```text
Wait one turn
Commit loop
```

There are no keyboard listeners because the game intentionally has no JavaScript.

## Ghost synchronization

Suppose the first recorded loop is:

```text
right
right
up
wait
left
```

During the next loop:

```text
current turn 1 -> Ghost 1 moves right
current turn 2 -> Ghost 1 moves right
current turn 3 -> Ghost 1 moves up
current turn 4 -> Ghost 1 waits
current turn 5 -> Ghost 1 moves left
```

After its recorded sequence ends, the ghost remains where it finished.

With several past loops, all ghosts replay simultaneously.

## Pressure plates

A pressure plate is:

```text
◆
```

If current-you or any ghost stands on it, the security door opens:

```text
▥ closed
▱ open
```

This is the main time-loop puzzle mechanic.

A common solution is:

1. make one loop that reaches the plate;
2. commit it;
3. start the next loop;
4. synchronize your movement with the ghost;
5. pass through while the old version of you keeps the plate active.

## Guards and cameras

Guards have a direction:

```text
▲ ▶ ▼ ◀
```

Cameras use:

```text
△ ▷ ▽ ◁
```

Their vision extends in a straight line and stops at:

- walls
- closed security doors

Ghosts do not trigger detection.

Only the current timeline can be caught.

This keeps ghosts useful as puzzle pieces instead of turning old runs into unavoidable failures.

## Objective

Each level contains:

```text
✦ target
⇥ exit
```

First steal the target.

Then return to the exit.

If you reach the exit without the target, the level does not complete.

## Timeline limit

You have at most:

```text
8 loops
```

If the timeline fills up, the run ends.

## Levels

```text
1. After Hours
2. Gallery Two
3. The Archive
```

Later levels add:

- more guards
- more cameras
- tighter corridors
- more difficult timing

## Pure PHP architecture

The entire game state lives in:

```php
$_SESSION['loop_heist_game']
```

PHP handles:

- player movement
- action recording
- deterministic replay
- ghost synchronization
- pressure plates
- doors
- guard patrols
- camera vision
- capture
- rewind
- level progression
- persistent statistics

Every turn is:

```text
POST
↓
PHP simulates one turn
↓
state saved to session
↓
redirect
↓
HTML rendered again
```

## Project structure

```text
loop-heist/
├── index.php
├── functions.php
├── style.css
├── LICENSE
└── README.md
```

## GitHub Pages

GitHub Pages cannot execute PHP.

Use PHP-capable hosting, a VPS, Docker/PHP-Apache, or PHP's built-in development server.

## License

MIT
