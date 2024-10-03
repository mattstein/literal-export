# Literal Export CLI Utility

This uses the [Literal](https://literal.club) [GraphQL API](https://literal.club/pages/api) to export more details than the account CSV.

It prompts for your account email address and password in order to get a GraphQL token and make subsequent queries. (These credentials are not stored!)

```
❯ php literal-export

 Account email address:
 > hi@example.foo

 Account password:
 >

Logging in...
Fetching reading states...
Fetching book data...
Compiling book information...
 220/220 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%
Writing JSON...
Done.
```

The slow part is where each book is queried for its reading start and end dates, which get folded into the resulting information.

This will write `literal-export.json`, with a single array of book objects:

```json
[
  {
    "title": "No Time to Spare",
    "subtitle": "Thinking about what Matters",
    "isbn10": "1328661598",
    "isbn13": "9781328661593",
    "publisher": null,
    "publishedDate": null,
    "authors":
    [
      "Ursula K. Le Guin"
    ],
    "pageCount": 240,
    "readingState": "FINISHED",
    "started": "2024-09-04",
    "finished": "2024-09-15"
  }
]
```
