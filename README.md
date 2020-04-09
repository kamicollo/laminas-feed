# Fork of laminas-feed 

This is a fork of `Laminas\Feed` that includes fixes & features to its PubSubHubBub component:
- General refactoring of subscriber class
- Fixes of various tests, introduction of missing tests
- Introduction of API to specify hub-specific headers, url parameters, and non-hub-specific headers
- Removed dependency from Laminas HTTP Client (can bring your own PSR7 client with a thin wrapper)

Because I messed up commit branches, commit order, and have introduced both API fixes and new features, it is not very likely this will ever make it into a pull request -_-