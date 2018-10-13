# celeste-bingo
A shared-seed bingo randomizer for Celeste speedruns!

## Usage

Powered by [Slim](https://www.slimframework.com/) and [SeedSpring](https://github.com/paragonie/seedspring), Bingo runs a seeded pesudo-RNG that provides bingo cards for Celeste speedruns. Common usage instructions can be found on the website itself, [here](https://oneninefour.cl/celeste/).

## API Endpoint

An API endpoint is available if you wish to obtain data and embed it in your own website/app/stream layout.

Calling the URL `https://oneninefour.cl/celeste/` via a POST request will return a JSON with the following format:

```javascript
{
    "seed":"fc56b42f3835b662",
    "list":{
        "Forsaken City":"Collect 5 strawberries",
        "Old Site":"Collect Crystal Heart",
        "Celestial Resort":"Collect a winged strawberry",
        "Golden Ridge":"Take the hidden path to Cliff Face",
        "Mirror Temple":"Kill a seeker",
        "Reflection":"Collect Cassette Tape",
        "Summit":"Take the rightmost path at 1000m"
    }
}
```

You can later call it again passing the seed as part of the URL (ex: `https://oneninefour.cl/celeste/fc56b42f3835b662`)

### Supported languages
['de', 'en', 'es', 'fr', 'it', 'ja', 'ko', 'ru', 'zh_hans']

## License

Source code is licensed under the MIT License.

## Special Thanks

Thanks to the Celeste development team for making a game I will never forget.  
Thanks to several friends that provided UX testing and assistance with front-end design.  
Special thanks to Mathemagician for seed logic.
Special thanks to [SebasContre](https://twitter.com/sebascontre) for additional coding and fixes.
