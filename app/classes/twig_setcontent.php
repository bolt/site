<?php

class Bolt_Setcontent_TokenParser extends Twig_TokenParser
{
    protected function convertToViewArguments(\Twig_Node_Expression_Array $array)
    {
        $arguments = array();

        foreach(array_chunk($array->getIterator()->getArrayCopy(), 2) as $pair) {
            if (count($pair) == 2) {
                $key   = $pair[0]->getAttribute('value');
                $value = $pair[1]->getAttribute('value');   // @todo support for multiple types

                $arguments[$key] = $value;
            }
        }

        return $arguments;
    }
    
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();

        $arguments = array();
        
        // name - the new variable with the results
        $name = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue();
        $this->parser->getStream()->expect(Twig_Token::OPERATOR_TYPE, '=');
        
        // contenttype, or simple expression to content.
        $contenttype = $this->parser->getExpressionParser()->parseExpression();

        if ($this->parser->getStream()->test(\Twig_Token::NAME_TYPE, 'where')) {
            $this->parser->getStream()->next();
            $where = $this->parser->getExpressionParser()->parseHashExpression();

            $arguments = $this->convertToViewArguments($where);
            
        }
        
        if ($this->parser->getStream()->test(\Twig_Token::NAME_TYPE, 'limit')) {
            $this->parser->getStream()->next();
            
            $limit = $this->parser->getExpressionParser()->parsePrimaryExpression()->getAttribute('value');                
            $arguments['limit'] = $limit;

        }

        if ($this->parser->getStream()->test(\Twig_Token::NAME_TYPE, 'order') ||
            $this->parser->getStream()->test(\Twig_Token::NAME_TYPE, 'orderby') ) {
            $this->parser->getStream()->next();
            
            $order = $this->parser->getExpressionParser()->parsePrimaryExpression()->getAttribute('value');                
            $arguments['order'] = $order;

        }

        if ($this->parser->getStream()->test(\Twig_Token::NAME_TYPE, 'paging') ||
            $this->parser->getStream()->test(\Twig_Token::NAME_TYPE, 'allowpaging') ) {
            $this->parser->getStream()->next();

            $arguments['paging'] = true;

        }

        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

        return new Bolt_Setcontent_Node($name, $contenttype, $arguments, $lineno, $this->getTag());
    }

    public function getTag()
    {
        return 'setcontent';
    }
}


class Bolt_Setcontent_Node extends Twig_Node
{
    public function __construct($name, $contenttype, $arguments, $lineno, $tag = null)
    {
        parent::__construct(array(), array('name' => $name, 'contenttype' => $contenttype, 'arguments' => $arguments),  $lineno, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        $arguments = $this->getAttribute('arguments');

        $compiler
            ->addDebugInfo($this)
            ->write('$template_storage = new Storage($context[\'app\']);' . "\n")
            ->write('$context[\'' . $this->getAttribute('name') . '\'] = ')
            ->write('$template_storage->getContent(')
            ->subcompile($this->getAttribute('contenttype'))
            ->raw(", " . var_export($arguments,true) )
            ->raw(" );\n")
        ;

    }
}