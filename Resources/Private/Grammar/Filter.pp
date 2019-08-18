%skip  space                \s

%token string               `(?:[^\`\\]|\\.)*\`
%token float                \-?(?:[0-9]+\.[0-9]+|\.[0-9]+)\b
%token integer              \-?(?:[0-9]|[1-9][0-9]+)\b
%token boolean              (?i)(true|false)\b

%token null                 (?i)null\b

%token and                  (?i)and\b
%token or                   (?i)or\b
%token not                  (?i)not\b
%token in                   (?i)in\b
%token match                (?i)match\b
%token on                   (?i)on\b

%token parenthesis_         \(
%token _parenthesis         \)
%token bracket_             \[
%token _bracket             \]
%token comma                ,
%token dot                  \.
%token dollar               \$

%token greater_than_equals  >=
%token less_than_equals     <=
%token not_equals           !=
%token equals               =
%token greater_than         >
%token less_than            <

%token identifier           [_A-Za-z][_0-9A-Za-z]*

#expression:
    primary()

primary:
    secondary() ( ::or:: #or primary() )?

secondary:
    ternary() ( ::and:: #and secondary() )?

ternary:
    ::parenthesis_:: primary() ::_parenthesis::
  | ::not:: #not primary()
  | field() ::equals:: #equals ( field() | variable() | scalar() )
  | ( field() | variable() | scalar() ) ::equals:: #equals field()
  | field() ::greater_than:: #greater_than ( field() | variable() | scalar() )
  | ( field() | variable() | scalar() ) ::greater_than:: #greater_than field()
  | field() ::less_than:: #less_than ( field() | variable() | scalar() )
  | ( field() | variable() | scalar() ) ::less_than:: #less_than field()
  | field() ::greater_than_equals:: #greater_than_equals ( field() | variable() | scalar() )
  | ( field() | variable() | scalar() ) ::greater_than_equals:: #greater_than_equals field()
  | field() ::less_than_equals:: #less_than_equals ( field() | variable() | scalar() )
  | ( field() | variable() | scalar() ) ::less_than_equals:: #less_than_equals field()
  | field() ::not_equals:: #not_equals ( field() | variable() | scalar() )
  | ( field() | variable() | scalar() ) ::not_equals:: #not_equals field()
  | field() ::in:: #in ( list() | variable() )
  | field() ::match:: #match ( <string> | variable() )

#field:
    path() constraint()?

#path:
    <identifier> ( ::dot:: <identifier> )*

#constraint:
    ::on:: <identifier>

#variable:
    ::dollar:: <identifier>

#list:
    ::bracket_:: ( integers() | strings() | floats() ) ::_bracket::

integers:
    <integer> ( ::comma:: ( <integer> | <null> ) )*

floats:
    <float> ( ::comma:: ( <float> | <null> ) )*

strings:
    <string> ( ::comma:: ( <string> | <null> ) )*

scalar:
    <null> | <boolean> | <float> | <integer> | <string>
