# Canopy JQL (JSON Query Language)

## JQL - Json Query Language

JQL is a json query language.

A single query looks like:
```json
{
  "field": "model.fieldname",
  "operator": "lt",
  "value": "value"
}
```

### Operators

* `lt` = `<`,
* `gt` = `>`,
* `lte` = `<=`,
* `gte` = `>=`,
* `eq` = `=`,
* `ne` = `!=`,
* `beginswith`
* `endswith`
* `contains`
* `in`

### Example:
```json
{
  "jql": [
    {
      "field": "mammals.A",
      "operator": "in",
      "value": ["A", "B", "C"]
    },
    {
      "field": "mammals.B",
      "operator": "eq",
      "value": "false"
    },
    {
      "OR": [
        {
          "field": "birds.C",
          "operator": "eq",
          "value": "true"
        },
        {
          "field": "mammals.D",
          "operator": "eq",
          "value": "true"
        },
        {
          "field": "mammals.E",
          "operator": "eq",
          "value": "true"
        },
        {
          "OR": [
            {
              "field": "dogs.F",
              "operator": "eq",
              "value": "true"
            },
            {
              "field": "dogs.G",
              "operator": "eq",
              "value": "true"
            },
            [
              {
                "field": "cats.H",
                "operator": "eq",
                "value": "true"
              },
              {
                "field": "cats.I",
                "operator": "eq",
                "value": "true"
              }
            ]
          ]
        }
      ]
    },
    {
      "field": "dogs.J",
      "operator": "eq",
      "value": "true"
    }
  ]
}
```



## Requirements

- PHP >=5.5


## Contributing

Contributions to the JQL package are welcome. Please note the following guidelines before submiting your pull request.

- Follow [PSR-2](http://www.php-fig.org/psr/psr-2/) coding standards.
- Write tests for new functions and added features

## License

Canopy/JQL is licensed under the [MIT License](http://opensource.org/licenses/MIT).
