// Formats amounts with resources/js/lib/money.ts for CentsMirrorTest.php.
// Reads a JSON list of nanodollar amounts on stdin, prints [{up, down}] as JSON.
import { formatCents } from '../../resources/js/lib/money.ts';

let input = '';
for await (const chunk of process.stdin) input += chunk;

process.stdout.write(
    JSON.stringify(
        JSON.parse(input).map((n) => ({
            up: formatCents(n, 'up'),
            down: formatCents(n, 'down'),
        })),
    ),
);
