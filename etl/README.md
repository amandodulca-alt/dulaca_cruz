additional questions:
  stock and remaining value health
  product line sales

new dim table (date_dim)
  paymentdate and orderdate have the same dates row by row
-removed

priceeach is between buyprice and MSRP
Each of the same products has the same price
payments.amount = quantityOrdered * priceeach

2 options. by period and by date. by period gives you Q1 Q2 Q3 Q4 H1 H2 Y. by date gives you 2 inputs of calendar from datefrom to dateto. separate option of country.
stock is table
market city is bar 
productlines is doughnut
best 8 products is bar
office sales is bar with 2 labels (revenue and orders)
