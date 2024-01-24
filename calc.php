<?php

class Calculator
{
    private array $variables = [];
    private int $scale = 10;

    const DEBUG = true;

    public function run()
    {
        while (true) {
            $input = readline('> ');

            if ($input === false) {
                break; // Exit on Ctrl+D or EOF
            }

            if (preg_match('/^\$[a-z_][a-z0-9_]*/i', $input, $matches)) {
                $this->processVariableDeclaration($matches[0], $input);
            } else {
                $result = $this->evaluateExpression($input);
                readline_add_history($input);
                echo '< ' . $result . PHP_EOL;
            }
        }
    }

    private function processVariableDeclaration(string $variable, string $input)
    {
        if (preg_match('/^\$[a-z_][a-z0-9_]*\s*=\s*(.*)$/', $input, $matches)) {
            $value = $this->evaluateExpression($matches[1]);
            $this->variables[$variable] = $value;
        }
    }

    private function evaluateExpression(string $expression)
    {
        $expression = $this->handleCommas($expression);
        $tokens = $this->tokenizeExpression($expression);

        var_dump($tokens);

        $tokens = $this->processFunctions($tokens);
        $postfix = $this->shuntingYard($tokens);
        $result = $this->evaluatePostfix($postfix);
        return $result;
    }

    private function handleCommas(string $expression)
    {
        $expression = preg_replace('/(\d)(,)(\d)/', '$1$3', $expression);
        return $expression;
    }

    private function processFunctions(array $tokens)
    {
        $functions = ['vars', 'ceil', 'floor', 'abs', 'max', 'min'];

        foreach ($tokens as &$token) {
            if (in_array($token, $functions)) {
                $token = $this->parseFunction($token, $tokens);
            }
        }

        return $tokens;
    }

    private function parseFunction(string $function, array &$tokens)
    {
        $argumentCount = 0;
        $functionArguments = [];

        foreach ($tokens as &$token) {
            if ($token === '(') {
                $argumentCount++;
            } elseif ($token === ')') {
                $argumentCount--;
                if ($argumentCount === 0) {
                    $result = $this->evaluateFunction($function, $functionArguments);
                    return $result;
                }
            } elseif ($argumentCount > 0) {
                $functionArguments[] = $token;
                $token = ''; // Remove processed function arguments from tokens
            }
        }

        return ''; // In case the function cannot be parsed correctly
    }


    private function evaluateFunction(string $function, array $arguments)
    {
        if(static::DEBUG) {
            echo "DEBUG: evaluate function '$function': "; var_dump($arguments); echo "\n\n";
        }
        switch ($function) {
            case 'ceil':
                return bccomp($arguments[0], '0', $this->scale) > 0 ? bcadd($arguments[0], '1', $this->scale) : $arguments[0];
            case 'floor':
                return bccomp($arguments[0], '0', $this->scale) < 0 ? bcsub($arguments[0], '1', $this->scale) : $arguments[0];
            case 'abs':
                return bccomp($arguments[0], '0', $this->scale) < 0 ? bcmul($arguments[0], '-1', $this->scale) : $arguments[0];
            case 'max':
                return max($arguments);
            case 'min':
                return min($arguments);
            case 'vars':
                var_dump($this->variables);
                return 0;
            default:
                return '';
        }
    }

    private function tokenizeExpression(string $expression)
    {
        $pattern = '/\s*('.implode('|', [
                '\$[a-z_][a-z0-9_]*', // Variables
                '\d*\.\d+|\d+', // Numbers
                '[+\-*\/%^(),]', // Operators and commas
            ]).')\s*/';

        preg_match_all($pattern, $expression, $matches);
        return $matches[1];
    }

    private function shuntingYard(array $tokens)
    {
        $output = $stack = [];

        $operators = [
            '+' => 1,
            '-' => 1,
            '*' => 2,
            '/' => 2,
            '^' => 3,
            '%' => 2,
        ];

        foreach ($tokens as $token) {
            if (is_numeric($token) || preg_match('/^\$[a-z_][a-z0-9_]*$/', $token)) {
                $output[] = $token;
            } elseif ($token === '(') {
                array_push($stack, $token);
            } elseif ($token === ')') {
                while (($top = array_pop($stack)) !== null && $top !== '(') {
                    $output[] = $top;
                }
            } elseif (isset($operators[$token])) {
                while (
                    !empty($stack) &&
                    isset($operators[end($stack)]) &&
                    $operators[$token] <= $operators[end($stack)]
                ) {
                    $output[] = array_pop($stack);
                }
                array_push($stack, $token);
            }
        }

        while (!empty($stack)) {
            $output[] = array_pop($stack);
        }

        return $output;
    }

    private function evaluatePostfix(array $postfix)
    {
        $stack = [];

        foreach ($postfix as $token) {
            if (is_numeric($token)) {
                array_push($stack, $token);
            } elseif (preg_match('/^\$[a-z_][a-z0-9_]*$/', $token)) {
                array_push($stack, $this->variables[$token] ?? '0');
            } else {
                $operand2 = array_pop($stack);
                $operand1 = array_pop($stack);

                switch ($token) {
                    case '+':
                        $result = bcadd($operand1, $operand2, $this->scale);
                        break;
                    case '-':
                        $result = bcsub($operand1, $operand2, $this->scale);
                        break;
                    case '*':
                        $result = bcmul($operand1, $operand2, $this->scale);
                        break;
                    case '/':
                        $result = bcdiv($operand1, $operand2, $this->scale);
                        break;
                    case '^':
                        $result = bcpow($operand1, $operand2, $this->scale);
                        break;
                    case '%':
                        $result = bcmod($operand1, $operand2);
                        break;
                    default:
                        $result = '0';
                }

                array_push($stack, $result);
            }
        }

        return end($stack);
    }
}

$calculator = new Calculator();
$calculator->run();
