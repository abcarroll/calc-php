<?php

class Calculator
{
    private array $variables = [];
    private int $scale = 10;

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
        $tokens = $this->tokenizeExpression($expression);
        $postfix = $this->shuntingYard($tokens);
        $result = $this->evaluatePostfix($postfix);
        return $result;
    }

    private function tokenizeExpression(string $expression)
    {
        $pattern = '/\s*('.implode('|', [
                '\$[a-z_][a-z0-9_]*', // Variables
                '\d*\.\d+|\d+', // Numbers
                '[+\-*\/%^\(\)]', // Operators
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
