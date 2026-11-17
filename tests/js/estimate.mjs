// Runs the prompt bar's estimate (resources/js/lib/pricing.ts) for PricingMirrorTest.php.
// Reads {"rules": PricingRules, "cases": EstimateSettings[]} on stdin, prints Estimate[] as JSON.
import { estimate } from '../../resources/js/lib/pricing.ts';

let input = '';
for await (const chunk of process.stdin) input += chunk;

const { rules, cases } = JSON.parse(input);
process.stdout.write(JSON.stringify(cases.map((c) => estimate(c, rules))));
