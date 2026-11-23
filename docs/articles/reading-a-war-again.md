# Reading a war, again

**From zero text to searchable to cross-referenced: what four albums of Anglo-Boer War newspaper cuttings taught us about machine reading.**

*Dr Johan Pieterse, The Archive and Heritage Digital Commons Group (AHG)*

Last month we digitised four bound albums of Anglo-Boer War newspaper cuttings for a client. Someone in 1899 and 1900 clipped the war reports as they came out and pasted them in, column after column, until the albums held 1,252 leaves. We photographed every leaf at 600 pixels per inch.

That gave us images, and an image of a page is silent. You can look at it, you can zoom into it, but you cannot search it, quote it or connect it to anything else. On day one the collection held exactly zero searchable words. This article is about getting from there to searchable text, and then to something more useful than searchable: text that knows what else it relates to.

## Zero text

Most digitisation projects stop at the image, or bolt on optical character recognition as an afterthought. We did the afterthought first. Tesseract, the usual open-source engine, read every page, and a language model then tidied up its output.

On century-old newsprint Tesseract struggles. The type is small, the paper has yellowed, and the print from the back of each cutting shows through. A heading that reads "Seaforth Highlanders' Brilliant Work" came back as "SECAFORTH HIGHNANDERS BRILLIANT WOLK". The tidying step made the text read far better, and that turned out to be the problem.

One cutting reports a party of Free Staters "apparently trekking for Van Reenen's Pass". The tidied text said they were "trying to get to" it. It is smooth and plausible, and it is not what was printed. A garbled OCR word announces itself. A fluent invention slips into the record and nobody notices, because there is nothing to notice. For a research collection that is worse than an error.

## Searchable

So we read everything again, differently. Instead of correcting another program's guesses, we asked a vision-language model (Qwen2.5-VL, run on our own GPUs, with nothing sent to an outside service) to read the photograph itself.

Each cutting was cut into strips about 1,200 pixels tall, split in the white space between lines so that no line was cut in half. The model read each strip, under instructions to keep the 1900 spelling and never to complete a word that is cut off on the paper. Tesseract stayed on, not as the reader but as a witness. Where the two disagreed badly, where a line Tesseract had found went missing, or where the model started repeating itself, a larger model read the strip again. Anything still doubtful was flagged in the log rather than accepted.

On a twelve-page test sample, the share of real dictionary words went from 89.1% with Tesseract alone, and 94.0% after the old tidying step, to 95.0% with the vision model. Most of what remains "wrong" is right: names such as Koodoosberg and Combrinck, Afrikaans terms such as kopje and donga, British spellings such as valour. The dictionary doesn't know them, but the page does.

The text then went back into the files as an invisible layer under the page images, so every volume is now a searchable PDF and a PDF/A for preservation. That step was harder than it sounds. The vision model gives text without positions, so its words had to be lined up against the word positions Tesseract had recorded, with a fallback for the cuttings where Tesseract had seen nothing useful. One PDF renderer silently drops any line whose box looks too tall for its text. We only found that because the spine pages came out blank, which is a good argument for checking the first and last page of every volume by hand.

## Cross-referenced

Searchable text answers the question you already know to ask. Cross-referenced text answers questions you didn't know you had.

Once the reading could be trusted, we extracted the people, places, military units and newspapers named in each cutting, keeping a name only if it appears word for word in that cutting's text. That produced 17,994 names across 4,993 cuttings, which we loaded into a small explorer that runs from the client's own drive with no server and no internet. Choose a name and it draws a map: the cuttings that mention it, and the other names those cuttings share. The war correspondent G. W. Steevens, for example, appears in eight cuttings spread across all four albums, one of them the notice of his death in besieged Ladysmith.

We also compared the wording of every cutting with every other. When two share at least 30% of their six-word phrases, they almost always carry the same telegram, printed by different papers and pasted twice. There are 94 such links between albums, and 85 of them involve the fourth album. That matters. We had argued, from its binding and its arrangement, that the fourth album was a separate compilation running alongside the other three rather than their continuation. The text now shows it independently, cutting by cutting.

The same comparison caught our own process. Four leaves had been photographed twice, so the leaves that belong in those slots may never have been photographed at all. Nobody had seen it in the images. Whole pages of identical cuttings gave it away.

## What we would tell anyone doing this

Machine reading is not an edition. We said so in the deliverable, kept the one hand correction we made (a misread spine date) apart from the machine text, and gave every cutting a one-click route back to its image. Names in casualty lists are where errors concentrate, and a reader who relies on one should check it against the page.

And run the models where the collection lives. These albums never left the building. The reading ran on hardware we can account for, which is the difference between telling a client their collection was processed securely and being able to show it.

The old pipeline made the text look right. The new one made it checkable.
