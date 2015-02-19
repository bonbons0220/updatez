dr12-post-types
===============

This basic WordPress plugin creates a custom post type for SDSS' Data Release 12 documentation, and a custom taxonomy for each SDSS Classification (Optical Spectra, Imaging, IR Spec, et al) . 

This allows the user to create separate posts for each SDSS Classification for better maintenance, and keeping the content separate from static pages that have non-DR12 related content.

Although each posts has its own unique URL, all the classifications get rendered on the root slug ("../spectro") as one concatenated page using WordPress' conditional tag for taxonomy. 

N.B.: Sidebar navigation must be added via the Widgets interface! Please see an existing widget for the correct markup, and double check that each post has appropriate and corresponding heading and section "id=" elements.
