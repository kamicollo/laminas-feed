# Fork of laminas-feed 

This is a fork of `Laminas\Feed` that strips it down to its PubSubHubBub component with heavy modifications:

- General refactoring, additional test coverage
- Introduction of Subscriber API to specify hub-specific headers, url parameters, and non-hub-specific headers
- PSR-7 / PSR-17 & PSR-18 friendly, uses PSR-7 requests and responses under the hood
- Support for hub_secrets
- Support for Pubsubhubbub 0.4 protocol and 0.3 protocol (per hub basis)

Because I made a spaghetti of commit branches, commit order, and have introduced both API fixes and new features, it is quite unlikely this will ever make it into pull requests 
back to the original laminas-feed project. -_-

Yet, this was a very enjoyable refactoring process, so hats off to the original creators!