%skip  space                \s

%token on                   (?i)on\b
%token order                (?i)(ascending|descending)\b

%token comma                ,
%token dot                  \.

%token identifier           [_A-Za-z][_0-9A-Za-z]*

#expression:
    field() ( ::comma:: field() )*

#field:
    path() constraint()? <order>

#path:
    <identifier> ( ::dot:: <identifier> )*

#constraint:
    ::on:: <identifier>
