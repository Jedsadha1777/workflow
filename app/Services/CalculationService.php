<?php

namespace App\Services;

class CalculationService
{
    public static function executeCalculations(array $formData, string $calculationScripts): array
    {
        if (empty($calculationScripts)) {
            return $formData;
        }

        $parser = new SimpleParser($calculationScripts, $formData);
        return $parser->execute();
    }
}

// ============ Tokenizer ============
class Token
{
    public string $type;
    public mixed $value;

    public function __construct(string $type, mixed $value)
    {
        $this->type = $type;
        $this->value = $value;
    }
}

class Tokenizer
{
    private string $input;
    private int $pos = 0;
    private int $length;

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->length = strlen($input);
    }

    public function tokenize(): array
    {
        $tokens = [];

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            // Skip whitespace
            if (ctype_space($char)) {
                $this->pos++;
                continue;
            }

            // Skip comments
            if ($char === '/' && $this->peek() === '/') {
                while ($this->pos < $this->length && $this->input[$this->pos] !== "\n") {
                    $this->pos++;
                }
                continue;
            }

            // Numbers
            if (ctype_digit($char) || ($char === '.' && ctype_digit($this->peek()))) {
                $tokens[] = $this->readNumber();
                continue;
            }

            // Identifiers & Keywords
            if (ctype_alpha($char) || $char === '_') {
                $tokens[] = $this->readIdentifier();
                continue;
            }

            // Strings
            if ($char === '"' || $char === "'") {
                $tokens[] = $this->readString($char);
                continue;
            }

            // Operators & Punctuation
            $twoChar = substr($this->input, $this->pos, 2);
            if ($twoChar === '||') {
                $tokens[] = new Token('LOGICAL_OR', '||');
                $this->pos += 2;
                continue;
            }
            if ($twoChar === '**') {
                $tokens[] = new Token('OPERATOR', '**');
                $this->pos += 2;
                continue;
            }

            // Single char operators
            if (in_array($char, ['+', '-', '*', '/'])) {
                $tokens[] = new Token('OPERATOR', $char);
                $this->pos++;
                continue;
            }
            
            // Punctuation
            if ($char === '(') {
                $tokens[] = new Token('LPAREN', '(');
                $this->pos++;
                continue;
            }
            if ($char === ')') {
                $tokens[] = new Token('RPAREN', ')');
                $this->pos++;
                continue;
            }
            if ($char === ',') {
                $tokens[] = new Token('COMMA', ',');
                $this->pos++;
                continue;
            }
            if ($char === ';') {
                $tokens[] = new Token('SEMICOLON', ';');
                $this->pos++;
                continue;
            }
            if ($char === '=') {
                $tokens[] = new Token('ASSIGN', '=');
                $this->pos++;
                continue;
            }
            if ($char === ':') {
                $tokens[] = new Token('COLON', ':');
                $this->pos++;
                continue;
            }

            // Unknown
            $this->pos++;
        }

        return $tokens;
    }

    private function readNumber(): Token
    {
        $start = $this->pos;
        while ($this->pos < $this->length && (ctype_digit($this->input[$this->pos]) || $this->input[$this->pos] === '.')) {
            $this->pos++;
        }
        $value = substr($this->input, $start, $this->pos - $start);
        return new Token('NUMBER', floatval($value));
    }

    private function readIdentifier(): Token
    {
        $start = $this->pos;
        while ($this->pos < $this->length && (ctype_alnum($this->input[$this->pos]) || $this->input[$this->pos] === '_')) {
            $this->pos++;
        }
        $value = substr($this->input, $start, $this->pos - $start);
        
        if (in_array($value, ['const', 'let', 'var', 'if', 'else'])) {
            return new Token('KEYWORD', $value);
        }
        
        return new Token('IDENTIFIER', $value);
    }

    private function readString(string $quote): Token
    {
        $this->pos++; // Skip opening quote
        $start = $this->pos;
        while ($this->pos < $this->length && $this->input[$this->pos] !== $quote) {
            $this->pos++;
        }
        $value = substr($this->input, $start, $this->pos - $start);
        $this->pos++; // Skip closing quote
        return new Token('STRING', $value);
    }

    private function peek(): string
    {
        return $this->pos + 1 < $this->length ? $this->input[$this->pos + 1] : '';
    }
}

// ============ AST Nodes ============
abstract class ASTNode
{
    abstract public function evaluate(array &$variables, array &$formData): mixed;
}

class NumberNode extends ASTNode
{
    public function __construct(public float $value) {}
    
    public function evaluate(array &$variables, array &$formData): float
    {
        return $this->value;
    }
}

class StringNode extends ASTNode
{
    public function __construct(public string $value) {}
    
    public function evaluate(array &$variables, array &$formData): string
    {
        return $this->value;
    }
}

class VariableNode extends ASTNode
{
    public function __construct(public string $name) {}
    
    public function evaluate(array &$variables, array &$formData): mixed
    {
        return $variables[$this->name] ?? 0;
    }
}

class BinaryOpNode extends ASTNode
{
    public function __construct(
        public ASTNode $left,
        public string $operator,
        public ASTNode $right
    ) {}
    
    public function evaluate(array &$variables, array &$formData): mixed
    {
        $left = $this->left->evaluate($variables, $formData);
        $right = $this->right->evaluate($variables, $formData);
        
        return match($this->operator) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right != 0 ? $left / $right : 0,
            '**' => $left ** $right,
            default => 0
        };
    }
}

class UnaryOpNode extends ASTNode
{
    public function __construct(
        public string $operator,
        public ASTNode $operand
    ) {}
    
    public function evaluate(array &$variables, array &$formData): mixed
    {
        $value = $this->operand->evaluate($variables, $formData);
        
        return match($this->operator) {
            '-' => -$value,
            '+' => +$value,
            default => $value
        };
    }
}

class LogicalOrNode extends ASTNode
{
    public function __construct(
        public ASTNode $left,
        public ASTNode $right
    ) {}
    
    public function evaluate(array &$variables, array &$formData): mixed
    {
        $left = $this->left->evaluate($variables, $formData);
        if ($left) return $left; // short-circuit
        return $this->right->evaluate($variables, $formData);
    }
}

class AssignmentNode extends ASTNode
{
    public function __construct(
        public string $varName,
        public ASTNode $value
    ) {}
    
    public function evaluate(array &$variables, array &$formData): mixed
    {
        $val = $this->value->evaluate($variables, $formData);
        $variables[$this->varName] = $val;
        return $val;
    }
}

class CallNode extends ASTNode
{
    public function __construct(
        public string $funcName,
        public array $args
    ) {}
    
    public function evaluate(array &$variables, array &$formData): mixed
    {
        $evaluatedArgs = array_map(fn($arg) => $arg->evaluate($variables, $formData), $this->args);
        
        return match($this->funcName) {
            'getValue' => $this->getValue($evaluatedArgs, $formData),
            'setValue' => $this->setValue($evaluatedArgs, $formData),
            'parseFloat' => floatval($evaluatedArgs[0] ?? 0),
            default => 0
        };
    }
    
    private function getValue(array $args, array $formData): mixed
    {
        $cellRef = $args[0] ?? '';
        $parts = explode(':', $cellRef);
        if (count($parts) !== 2) return '';
        
        [$sheet, $cell] = $parts;
        return $formData[$sheet][$cell] ?? '';
    }
    
    private function setValue(array $args, array &$formData): mixed
    {
        $cellRef = $args[0] ?? '';
        $value = $args[1] ?? '';
        
        $parts = explode(':', $cellRef);
        if (count($parts) !== 2) return $value;
        
        [$sheet, $cell] = $parts;
        if (!isset($formData[$sheet])) {
            $formData[$sheet] = [];
        }
        $formData[$sheet][$cell] = $value;
        
        return $value;
    }
}

// ============ Parser ============
class SimpleParser
{
    private array $tokens;
    private int $pos = 0;
    private array $formData;
    private int $evalCount = 0;
    private const MAX_EVALS = 10000;

    public function __construct(string $code, array $formData)
    {
        $tokenizer = new Tokenizer($code);
        $this->tokens = $tokenizer->tokenize();
        $this->formData = $formData;
    }

    public function execute(): array
    {
        $variables = [];
        
        while ($this->pos < count($this->tokens)) {
            if (++$this->evalCount > self::MAX_EVALS) {
                throw new \Exception('Execution limit exceeded (possible infinite loop)');
            }
            
            $stmt = $this->parseStatement();
            if ($stmt) {
                $stmt->evaluate($variables, $this->formData);
            }
        }
        
        return $this->formData;
    }

    private function parseStatement(): ?ASTNode
    {
        $token = $this->current();
        
        if (!$token) return null;
        
        // const/let/var declaration
        if ($token->type === 'KEYWORD' && in_array($token->value, ['const', 'let', 'var'])) {
            $this->advance();
            $varName = $this->expect('IDENTIFIER')->value;
            $this->expect('ASSIGN');
            $value = $this->parseExpression();
            $this->skipSemicolon();
            return new AssignmentNode($varName, $value);
        }
        
        // Expression statement
        $expr = $this->parseExpression();
        $this->skipSemicolon();
        return $expr;
    }

    private function parseExpression(): ASTNode
    {
        return $this->parseLogicalOr();
    }

    // Precedence level 1: || (lowest)
    private function parseLogicalOr(): ASTNode
    {
        $left = $this->parseAdditive();
        
        while ($this->current() && $this->current()->type === 'LOGICAL_OR') {
            $this->advance();
            $right = $this->parseAdditive();
            $left = new LogicalOrNode($left, $right);
        }
        
        return $left;
    }

    // Precedence level 2: + -
    private function parseAdditive(): ASTNode
    {
        $left = $this->parseMultiplicative();
        
        while ($this->current() && $this->current()->type === 'OPERATOR' 
               && in_array($this->current()->value, ['+', '-'])) {
            $op = $this->advance()->value;
            $right = $this->parseMultiplicative();
            $left = new BinaryOpNode($left, $op, $right);
        }
        
        return $left;
    }

    // Precedence level 3: * /
    private function parseMultiplicative(): ASTNode
    {
        $left = $this->parsePower();
        
        while ($this->current() && $this->current()->type === 'OPERATOR' 
               && in_array($this->current()->value, ['*', '/'])) {
            $op = $this->advance()->value;
            $right = $this->parsePower();
            $left = new BinaryOpNode($left, $op, $right);
        }
        
        return $left;
    }

    // Precedence level 4: ** (right-associative)
    private function parsePower(): ASTNode
    {
        $left = $this->parseUnary();
        
        if ($this->current() && $this->current()->type === 'OPERATOR' 
            && $this->current()->value === '**') {
            $this->advance();
            $right = $this->parsePower(); // recursive for right-associative
            return new BinaryOpNode($left, '**', $right);
        }
        
        return $left;
    }

    // Precedence level 5: unary +/-
    private function parseUnary(): ASTNode
    {
        $token = $this->current();
        
        if ($token && $token->type === 'OPERATOR' && in_array($token->value, ['+', '-'])) {
            $op = $this->advance()->value;
            $operand = $this->parseUnary(); // recursive for multiple unary
            return new UnaryOpNode($op, $operand);
        }
        
        return $this->parsePrimary();
    }

    private function parsePrimary(): ASTNode
    {
        $token = $this->current();
        
        if (!$token) {
            return new NumberNode(0);
        }
        
        // Number
        if ($token->type === 'NUMBER') {
            $this->advance();
            return new NumberNode($token->value);
        }
        
        // String
        if ($token->type === 'STRING') {
            $this->advance();
            return new StringNode($token->value);
        }
        
        // Function call
        if ($token->type === 'IDENTIFIER' && $this->peek() && $this->peek()->type === 'LPAREN') {
            return $this->parseFunctionCall();
        }
        
        // Variable
        if ($token->type === 'IDENTIFIER') {
            $this->advance();
            return new VariableNode($token->value);
        }
        
        // Parentheses
        if ($token->type === 'LPAREN') {
            $this->advance();
            $expr = $this->parseExpression();
            $this->expect('RPAREN');
            return $expr;
        }
        
        return new NumberNode(0);
    }

    private function parseFunctionCall(): CallNode
    {
        $funcName = $this->advance()->value;
        $this->expect('LPAREN');
        
        $args = [];
        while ($this->current() && $this->current()->type !== 'RPAREN') {
            $args[] = $this->parseExpression();
            if ($this->current() && $this->current()->type === 'COMMA') {
                $this->advance();
            }
        }
        
        $this->expect('RPAREN');
        
        return new CallNode($funcName, $args);
    }

    private function current(): ?Token
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function peek(): ?Token
    {
        return $this->tokens[$this->pos + 1] ?? null;
    }

    private function advance(): ?Token
    {
        $token = $this->current();
        $this->pos++;
        return $token;
    }

    private function expect(string $type, mixed $value = null): Token
    {
        $token = $this->current();
        if (!$token || $token->type !== $type || ($value !== null && $token->value !== $value)) {
            throw new \Exception("Expected $type" . ($value ? " '$value'" : ''));
        }
        return $this->advance();
    }

    private function skipSemicolon(): void
    {
        if ($this->current() && $this->current()->type === 'SEMICOLON') {
            $this->advance();
        }
    }
}