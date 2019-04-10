# babylib
Babylib is an MVC framework for WordPress Plugins

# Reasoning
WordPress plugins are almost universally cobbled together out of flat functions. Worse, they tend to rely heavily on the WordPress API, and the WP API is really just a bit dodgy (innit mate). Functions that return false if there's an error. That kind of thing.

This is my attempt to abstract that kruft away and roll my own persistence layer (that part was just for fun).

I also wanted to allow plugin composition via composer, so I could deliver apps that consisted of a bunch of plugins tied together by an overall plugin that consisted only of a `composer.json` and some DB config files. That part seems to work pretty nicely.

The `BabylonModel` hierarchy could be made nicer if it used the Active Record pattern. Still, it works fine for what I needed.

I no longer work with WordPress, so I'm not actively maintaining this project. Feel free to raise issues and I'll try and answer, but won't be fixing any bugs or adding any features. Feel free to fork it yourself if you want something changed!
