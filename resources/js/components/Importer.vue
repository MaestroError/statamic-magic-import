<script>
export default {
  props: {
    token: String,
  },

  data: function () {
    return {
      importing: false,
      imported: false,
      importFailed: false,
      importError: null,
      summary: null,
      showAllPages: false,
      showCollections: [],
      showTaxonomies: [],
      // importing in collection
      importTypes: {},
      collectionPairs: {},
      collectionFieldPairs: {},
      existingCollections: null,

      counter: null,
      hours: 0,
      minutes: 0,
      seconds: 0,
    };
  },

  computed: {
    totalPages: function () {
      return this.summary.pages && Object.keys(this.summary.pages).length;
    },
    showPagesSection: function () {
      return (
        this.summary &&
        this.summary.pages &&
        !this.importing &&
        !this.imported &&
        !this.importFailed
      );
    },
    showCollectionsSection: function () {
      return this.summary && this.summary.collections;
    },
    showTaxonomiesSection: function () {
      return this.summary && this.summary.taxonomies;
    },
    totalEntries: function () {
      return this.calculateTotalEntries();
    },
  },

  mounted() {
    this.summary = window.ImportSummary;
    this.existingCollections = window.existingCollections
    console.log(this.existingCollections);

    if (this.summary.collections) {
      Object.keys(this.summary.collections).forEach((key) => {
        this.importTypes[key] = "new"
      })
      console.log(this.importTypes);
    }
  },

  methods: {
    setCollectionImportType(collectionName, event) {
      console.log(collectionName, event.target.value);
      this.$set(this.importTypes, collectionName, event.target.value)
      this.$forceUpdate()
    },
    setCollectionPair(collectionName, event) {
      // collectionName is collection from json and event gives a collection from Statamic
      this.collectionPairs[collectionName] = event.target.value

      let fields = this.existingCollections.filter((coll) => {
        console.log(coll.handle, event.target.value);
        return coll.handle == event.target.value
      })[0].handles_from_blueprint

      this.$set(this.collectionPairs, collectionName+"_fields", fields)
      
      setInterval(() => {
        const options = document.querySelectorAll('#data-options option');
        options.forEach(option => {
            if (option.dataset.collectionName !== collectionName) {
                option.style.display = 'none';
            }
        });
      }, 500);

      this.$forceUpdate()
    },
    // collectionName is from json, field is from choosen existing collection and event gives connected field from JSON 
    setFieldPair(collectionName, field, event) {
      let fieldFromJson = event.target.value
      if (this.collectionFieldPairs[collectionName]) {
        this.collectionFieldPairs[collectionName][fieldFromJson] = field
      } else {
        let fields = {}
        fields[fieldFromJson] = field
        this.collectionFieldPairs[collectionName] = fields
      }
      console.log(this.collectionFieldPairs);
    },
    startImport: function () {
      this.importing = true;
      this.imported = false;
      this.importFailed = false;
      this.importError = null;

      this.$progress.start("json-import");

      this.startTimer();

      fetch(cp_url("json-import/import"), {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          _token: this.token,
          summary: this.summary,
          collectionPairs: this.collectionPairs,
          collectionFieldPairs: this.collectionFieldPairs,
        }),
      })
        .then((response) => {
          this.importing = false;

          if (response.ok) {
            this.imported = true;
          } else {
            this.importFailed = true;
            this.importError =
              response.statusText + " (" + response.status + ")";
          }

          this.$progress.complete("json-import");

          this.stopTimer();

          return response.json();
        })
        .then((data) => {
          console.log(data);
        });
    },

    hasDuplicates(collection) {
      return !!this.duplicateCount(collection);
    },

    duplicateCount: function (items = {}) {
      let count = 0;

      items = Object.values(items);

      if (!items || !Array.isArray(items)) return count;

      items.forEach((item) => {
        if (!item.exists) {
          return;
        }

        count++;
      });

      return count;
    },

    uncheckDuplicates: function (items = {}) {
      items = Object.values(items);
      if (!items.length || !Array.isArray(items)) return;

      items.forEach((item) => {
        if (!item.exists) {
          return;
        }

        item._checked = false;
      });
    },

    uncheckAll: function (items = {}) {
      items = Object.values(items);
      if (!items.length || !Array.isArray(items)) return;
      items.forEach((item) => {
        item._checked = false;
      });
    },

    checkAll: function (items = {}) {
      items = Object.values(items);
      if (!items.length || !Array.isArray(items)) return;
      items.forEach((item) => {
        item._checked = true;
      });
    },

    size: function (obj) {
      return Object.keys(obj).length;
    },

    showCollection: function (collection) {
      this.showCollections.push(collection);
      this.showCollections = [...new Set(this.showCollections)];
    },

    hideCollection: function (hidden) {
      this.showCollections = this.showCollections.filter((c) => {
        return c !== hidden;
      });
    },

    shouldShowCollection: function (collection) {
      return this.showCollections.includes(collection);
    },

    shouldShowImportForm: function (collectionName) {
      return this.importTypes[collectionName] == 'import'
    },

    showTaxonomy: function (taxonomy) {
      this.showTaxonomies.push(taxonomy);
      this.showTaxonomies = [...new Set(this.showTaxonomies)];
    },

    hideTaxonomy: function (hidden) {
      this.showTaxonomies = this.showTaxonomies.filter((t) => {
        return t !== hidden;
      });
    },

    shouldShowTaxonomy: function (taxonomy) {
      return this.showTaxonomies.includes(taxonomy);
    },

    calculateTotalEntries: function () {
      let totalEntries = 0;

      if (this.summary.pages) {
        Object.keys(this.summary.pages).forEach((key) => {
          if (this.summary.pages[key]["_checked"]) {
            totalEntries++;
          }
        });
      }

      if (this.summary.collections) {
        Object.keys(this.summary.collections).forEach((key) => {
          Object.keys(this.summary.collections[key]["entries"]).forEach(
            (entry) => {
              if (this.summary.collections[key]["entries"][entry]["_checked"]) {
                totalEntries++;
              }
            }
          );
        });
      }

      if (this.summary.taxonomies) {
        Object.keys(this.summary.taxonomies).forEach((key) => {
          Object.keys(this.summary.taxonomies[key]["terms"]).forEach(
            (entry) => {
              if (this.summary.taxonomies[key]["terms"][entry]["_checked"]) {
                totalEntries++;
              }
            }
          );
        });
      }

      return totalEntries;
    },

    startTimer() {
      this.minutes = this.checkSingleDigit(0);
      this.seconds = this.checkSingleDigit(0);

      this.counter = setInterval(() => {
        const date = new Date(
          0,
          0,
          0,
          parseInt(this.hours),
          parseInt(this.minutes),
          parseInt(this.seconds) + 1
        );
        this.hours = date.getHours();
        this.minutes = this.checkSingleDigit(date.getMinutes());
        this.seconds = this.checkSingleDigit(date.getSeconds());
      }, 1000);
    },

    stopTimer() {
      clearInterval(this.counter);
    },

    checkSingleDigit(digit) {
      return ("0" + digit).slice(-2);
    },
  },
};
</script>

<style>
/* Reset the padding and margin */
.field-pairs,
.field-item {
    padding: 0;
    margin: 0;
}

/* Style for the container that will act as a table */
.field-pairs {
    display: table;
    width: 100%; /* You can adjust the width as per your need */
    border: 1px solid gray;
    border-collapse: collapse; /* Ensures that borders are combined */
}

/* Style for the container that will act as a table row */
.field-pairs,
.form-group.pb-0.field-pairs {
    display: table-row;
}

/* Style for the container that will act as a table cell */
.field-item,
.existing-collection-field,
.json-collection-field {
    display: table-cell;
    border: 1px solid gray;
    padding: 8px; /* Adjust padding as per your need */
    text-align: center; /* Center-align the text */
    vertical-align: middle; /* Center-align vertically */
}

/* Style for the equals sign */
.equals-to {
    display: inline-block;
    vertical-align: middle;
}
</style>